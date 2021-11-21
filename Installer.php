<?php
/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\composer;

use Composer\Package\PackageInterface;
use Composer\Installer\LibraryInstaller;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Util\Filesystem;
use React\Promise\PromiseInterface;

/**
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Installer extends LibraryInstaller
{
    public const EXTRA_BOOTSTRAP = 'bootstrap';
    public const EXTENSION_FILE = 'ziiframework/extensions.php';


    /**
     * @inheritdoc
     */
    public function supports($packageType): bool
    {
        return $packageType === 'yii2-extension';
    }

    /**
     * @inheritdoc
     */
    public function install(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        $afterInstall = function () use ($package) {
            // add the package to ziiframework/extensions.php
            $this->addPackage($package);
            // ensure the yii2-dev package also provides Yii.php in the same place as yii2 does
            if ($package->getName() === 'ziiframework/zii') {
                $this->linkBaseYiiFiles();
            }
        };

        // install the package the normal composer way
        $promise = parent::install($repo, $package);

        // Composer v2 might return a promise here
        if ($promise instanceof PromiseInterface) {
            return $promise->then($afterInstall);
        }

        // If not, execute the code right away as parent::install executed synchronously (composer v1, or v2 without async)
        $afterInstall();
    }

    /**
     * @inheritdoc
     */
    public function update(InstalledRepositoryInterface $repo, PackageInterface $initial, PackageInterface $target)
    {
        $afterUpdate = function () use ($initial, $target) {
            $this->removePackage($initial);
            $this->addPackage($target);
            // ensure the yii2-dev package also provides Yii.php in the same place as yii2 does
            if ($initial->getName() === 'ziiframework/zii') {
                $this->linkBaseYiiFiles();
            }
        };

        // update the package the normal composer way
        $promise = parent::update($repo, $initial, $target);

        // Composer v2 might return a promise here
        if ($promise instanceof PromiseInterface) {
            return $promise->then($afterUpdate);
        }

        // If not, execute the code right away as parent::update executed synchronously (composer v1, or v2 without async)
        $afterUpdate();
    }

    /**
     * @inheritdoc
     */
    public function uninstall(InstalledRepositoryInterface $repo, PackageInterface $package)
    {
        $afterUninstall = function () use ($package) {
            // remove the package from ziiframework/extensions.php
            $this->removePackage($package);
            // remove links for Yii.php
            if ($package->getName() === 'ziiframework/zii') {
                $this->removeBaseYiiFiles();
            }
        };

        // uninstall the package the normal composer way
        $promise = parent::uninstall($repo, $package);

        // Composer v2 might return a promise here
        if ($promise instanceof PromiseInterface) {
            return $promise->then($afterUninstall);
        }

        // If not, execute the code right away as parent::uninstall executed synchronously (composer v1, or v2 without async)
        $afterUninstall();
    }

    protected function addPackage(PackageInterface $package): void
    {
        $extension = [
            'name' => $package->getName(),
            'version' => $package->getVersion(),
        ];

        $alias = $this->generateDefaultAlias($package);
        if (!empty($alias)) {
            $extension['alias'] = $alias;
        }
        $extra = $package->getExtra();
        if (isset($extra[self::EXTRA_BOOTSTRAP])) {
            $extension['bootstrap'] = $extra[self::EXTRA_BOOTSTRAP];
        }

        $extensions = $this->loadExtensions();
        $extensions[$package->getName()] = $extension;
        $this->saveExtensions($extensions);
    }

    protected function generateDefaultAlias(PackageInterface $package): array
    {
        $fs = new Filesystem;
        $vendorDir = $fs->normalizePath($this->vendorDir);
        $autoload = $package->getAutoload();

        $aliases = [];

        if (!empty($autoload['psr-0'])) {
            foreach ($autoload['psr-0'] as $name => $path) {
                $name = str_replace('\\', '/', trim($name, '\\'));
                if (!$fs->isAbsolutePath($path)) {
                    $path = $this->vendorDir . '/' . $package->getPrettyName() . '/' . $path;
                }
                $path = $fs->normalizePath($path);
                if (strpos($path . '/', $vendorDir . '/') === 0) {
                    $aliases["@$name"] = '<vendor-dir>' . substr($path, strlen($vendorDir)) . '/' . $name;
                } else {
                    $aliases["@$name"] = $path . '/' . $name;
                }
            }
        }

        if (!empty($autoload['psr-4'])) {
            foreach ($autoload['psr-4'] as $name => $path) {
                if (is_array($path)) {
                    // ignore psr-4 autoload specifications with multiple search paths
                    // we can not convert them into aliases as they are ambiguous
                    continue;
                }
                $name = str_replace('\\', '/', trim($name, '\\'));
                if (!$fs->isAbsolutePath($path)) {
                    $path = $this->vendorDir . '/' . $package->getPrettyName() . '/' . $path;
                }
                $path = $fs->normalizePath($path);
                if (strpos($path . '/', $vendorDir . '/') === 0) {
                    $aliases["@$name"] = '<vendor-dir>' . substr($path, strlen($vendorDir));
                } else {
                    $aliases["@$name"] = $path;
                }
            }
        }

        return $aliases;
    }

    protected function removePackage(PackageInterface $package): void
    {
        $packages = $this->loadExtensions();
        unset($packages[$package->getName()]);
        $this->saveExtensions($packages);
    }

    protected function loadExtensions()
    {
        $file = $this->vendorDir . '/' . static::EXTENSION_FILE;
        if (!is_file($file)) {
            return [];
        }
        // invalidate opcache of extensions.php if exists
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($file, true);
        }
        $extensions = require($file);

        $vendorDir = str_replace('\\', '/', $this->vendorDir);
        $n = strlen($vendorDir);

        foreach ($extensions as &$extension) {
            if (isset($extension['alias'])) {
                foreach ($extension['alias'] as $alias => $path) {
                    $path = str_replace('\\', '/', $path);
                    if (strpos($path . '/', $vendorDir . '/') === 0) {
                        $extension['alias'][$alias] = '<vendor-dir>' . substr($path, $n);
                    }
                }
            }
        }

        return $extensions;
    }

    protected function saveExtensions(array $extensions): void
    {
        $file = $this->vendorDir . '/' . static::EXTENSION_FILE;
        if (!file_exists(dirname($file))) {
            mkdir(dirname($file), 0777, true);
        }
        $array = str_replace("'<vendor-dir>", '$vendorDir . \'', var_export($extensions, true));
        file_put_contents($file, "<?php\n\n\$vendorDir = dirname(__DIR__);\n\nreturn $array;\n");
        // invalidate opcache of extensions.php if exists
        if (function_exists('opcache_invalidate')) {
            @opcache_invalidate($file, true);
        }
    }

    protected function linkBaseYiiFiles(): void
    {
        $yiiDir = $this->vendorDir . '/ziiframework/zii/src';
        if (!file_exists($yiiDir)) {
            mkdir($yiiDir, 0777, true);
        }
        foreach (['Yii.php', 'BaseYii.php', 'classes.php'] as $file) {
            file_put_contents($yiiDir . '/' . $file, <<<EOF
<?php
/**
 * This is a link provided by the ziiframework/zii package via ziiframework/composer plugin.
 *
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

return require(__DIR__ . '/../zii/src/$file');

EOF
            );
        }
    }

    protected function removeBaseYiiFiles(): void
    {
        $yiiDir = $this->vendorDir . '/ziiframework/zii/src';
        foreach (['Yii.php', 'BaseYii.php', 'classes.php'] as $file) {
            if (file_exists($yiiDir . '/' . $file)) {
                unlink($yiiDir . '/' . $file);
            }
        }
        if (file_exists($yiiDir)) {
            rmdir($yiiDir);
        }
    }
}
