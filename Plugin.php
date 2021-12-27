<?php

declare(strict_types=1);

/**
 * @link http://www.yiiframework.com/
 * @copyright Copyright (c) 2008 Yii Software LLC
 * @license http://www.yiiframework.com/license/
 */

namespace yii\composer;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;

/**
 * Plugin is the composer plugin that registers the Yii composer installer.
 *
 * @author Qiang Xue <qiang.xue@gmail.com>
 * @since 2.0
 */
class Plugin implements PluginInterface, EventSubscriberInterface
{
    /**
     * @var Installer
     */
    private $_installer;

    /**
     * @inheritdoc
     */
    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->_installer = new Installer($io, $composer);

        $composer->getInstallationManager()->addInstaller($this->_installer);

        $vendorDir = rtrim($composer->getConfig()->get('vendor-dir'), '/');

        $file = $vendorDir . '/ziiframework/extensions.php';

        if (!is_file($file)) {
            if (!is_dir(dirname($file))) {
                mkdir(dirname($file), 0777, true);
            }
            file_put_contents($file, "<?php\n\nreturn [];\n");
        }
    }

    /**
     * @inheritdoc
     */
    public function deactivate(Composer $composer, IOInterface $io): void
    {
        $composer->getInstallationManager()->removeInstaller($this->_installer);
    }

    /**
     * @inheritdoc
     */
    public function uninstall(Composer $composer, IOInterface $io): void
    {
    }

    /**
     * @inheritdoc
     * @return array The event names to listen to.
     */
    public static function getSubscribedEvents(): array
    {
        return [];
    }
}
