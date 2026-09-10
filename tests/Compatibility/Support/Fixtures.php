<?php

namespace LaravelSabre\Tests\Compatibility\Support;

use LaravelSabre\Http\Auth\AuthBackend;
use LaravelSabre\LaravelSabre;
use LaravelSabre\Tests\Sabre\DAVACL\PrincipalBackend\Mock as PrincipalBackend;
use Sabre\CardDAV\Plugin as CardDAVPlugin;
use Sabre\DAV\Auth\Plugin as AuthPlugin;
use Sabre\DAV\FS\Directory;
use Sabre\DAV\Locks\Backend\File as LockBackend;
use Sabre\DAV\Locks\Plugin as LocksPlugin;
use Sabre\DAV\Tree;
use Sabre\DAVACL\PrincipalCollection;

/**
 * Registers the resource tree and plugins for a named compatibility fixture.
 *
 * The same fixtures are used when recording against 1.x and when replaying against the rebuild, so a
 * difference in the result is a difference in the package, not in the setup.
 */
final class Fixtures
{
    public static function root(): string
    {
        return sys_get_temp_dir().'/laravel-sabre-compat';
    }

    private static function lockFile(): string
    {
        return sys_get_temp_dir().'/laravel-sabre-compat-locks.db';
    }

    /**
     * Build a deterministic filesystem tree: the same names and contents on every run.
     */
    public static function prepareFiles(): string
    {
        $root = self::root();
        self::remove($root);
        mkdir($root, 0777, true);
        file_put_contents($root.'/hello.txt', "It's magical\n");
        file_put_contents($root.'/hello world.txt', "spaces are allowed\n");
        mkdir($root.'/sub');
        file_put_contents($root.'/sub/nested.txt', "nested\n");

        @unlink(self::lockFile());

        return $root;
    }

    public static function cleanUp(): void
    {
        self::remove(self::root());
        @unlink(self::lockFile());
    }

    public static function apply(string $fixture): void
    {
        switch ($fixture) {
            case 'empty':
                return;

            case 'principals':
                LaravelSabre::nodes([new PrincipalCollection(new PrincipalBackend())]);
                LaravelSabre::plugin(new CardDAVPlugin());

                return;

            case 'files':
                LaravelSabre::nodes(new Tree(new Directory(self::prepareFiles())));

                return;

            case 'locks':
                LaravelSabre::nodes(new Tree(new Directory(self::prepareFiles())));
                LaravelSabre::plugin(new LocksPlugin(new LockBackend(self::lockFile())));

                return;

            case 'auth-required':
                LaravelSabre::nodes(new Tree(new Directory(self::prepareFiles())));
                LaravelSabre::plugin(new AuthPlugin(new AuthBackend()));

                return;

            case 'denied':
                LaravelSabre::nodes(new Tree(new Directory(self::prepareFiles())));
                LaravelSabre::auth(function (): bool {
                    return false;
                });

                return;

            default:
                throw new \InvalidArgumentException('Unknown compatibility fixture: '.$fixture);
        }
    }

    private static function remove(string $path): void
    {
        if (! is_dir($path)) {
            return;
        }

        $entries = scandir($path) ?: [];
        foreach ($entries as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $full = $path.'/'.$entry;
            is_dir($full) ? self::remove($full) : @unlink($full);
        }

        @rmdir($path);
    }
}
