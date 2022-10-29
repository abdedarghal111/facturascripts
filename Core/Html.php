<?php
/**
 * This file is part of FacturaScripts
 * Copyright (C) 2017-2022 Carlos Garcia Gomez <carlos@facturascripts.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Lesser General Public License as
 * published by the Free Software Foundation, either version 3 of the
 * License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 */

namespace FacturaScripts\Core;

use FacturaScripts\Core\App\AppSettings;
use FacturaScripts\Core\Base\DivisaTools;
use FacturaScripts\Core\Base\MiniLog;
use FacturaScripts\Core\Base\PluginManager;
use FacturaScripts\Core\Base\Translator;
use FacturaScripts\Core\DataSrc\Divisas;
use FacturaScripts\Core\Lib\AssetManager;
use FacturaScripts\Core\Lib\MultiRequestProtection;
use FacturaScripts\Core\Model\AttachedFile;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFunction;

/**
 * @author Carlos García Gómez      <carlos@facturascripts.com>
 * @author Daniel Fernández Giménez <hola@danielfg.es>
 */
final class Html
{
    /** @var array */
    private static $functions = [];

    /** @var FilesystemLoader */
    private static $loader;

    /** @var array */
    private static $paths = [];

    /** @var bool */
    private static $plugins = true;

    /** @var Environment */
    private static $twig;

    public static function addFunction(TwigFunction $function): void
    {
        self::$functions[] = $function;
    }

    public static function addPath(string $name, string $path): void
    {
        self::$paths[$name] = $path;
    }

    public static function disablePlugins(bool $disable = true): void
    {
        self::$plugins = !$disable;
    }

    /**
     * @throws SyntaxError
     * @throws RuntimeError
     * @throws LoaderError
     */
    public static function render(string $template, array $params = []): string
    {
        $templateVars = [
            'appSettings' => new AppSettings(),
            'assetManager' => new AssetManager(),
            'i18n' => new Translator(),
            'log' => new MiniLog()
        ];
        return self::twig()->render($template, array_merge($params, $templateVars));
    }

    private static function assetFunction(): TwigFunction
    {
        return new TwigFunction('asset', function ($string) {
            $path = FS_ROUTE . '/';
            return substr($string, 0, strlen($path)) == $path ?
                $string :
                str_replace('//', '/', $path . $string);
        });
    }

    private static function attachedFileFunction(): TwigFunction
    {
        return new TwigFunction('attachedFile', function ($id) {
            $attached = new AttachedFile();
            $attached->loadFromCode($id);
            return $attached;
        });
    }

    private static function formTokenFunction(): TwigFunction
    {
        return new TwigFunction('formToken', function (bool $input = true) {
            $tokenClass = new MultiRequestProtection();
            return $input ?
                '<input type="hidden" name="multireqtoken" value="' . $tokenClass->newToken() . '"/>' :
                $tokenClass->newToken();
        });
    }

    private static function getIncludeViews(): TwigFunction
    {
        return new TwigFunction('getIncludeViews', function (string $fileParent, string $position) {
            $files = [];
            $fileParentTemp = explode('/', $fileParent);
            $fileParent = str_replace('.html.twig', '', end($fileParentTemp));
            $pluginManager = new PluginManager();

            foreach ($pluginManager->enabledPlugins() as $pluginName) {
                $path = FS_FOLDER . '/Plugins/' . $pluginName . '/Extension/View/';
                if (false === file_exists($path)) {
                    continue;
                }

                $ficheros = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path));
                foreach ($ficheros as $f) {
                    if ($f->isDir()) {
                        continue;
                    }

                    $file = explode('_', str_replace('.html.twig', '', $f->getFilename()));
                    if (count($file) <= 1) {
                        continue;
                    }

                    // comprobamos que el archivo empiece por el nombre del fichero que se está incluyendo
                    if ($file[0] !== $fileParent) {
                        continue;
                    }

                    // comprobamos que la posición del archivo sea la solicitada
                    if ($file[1] !== $position) {
                        continue;
                    }

                    $arrayFile = [
                        'path' => '@PluginExtension' . $pluginName . '/' . $f->getFilename(),
                        'file' => $file[0],
                        'position' => $file[1]
                    ];

                    if (false === isset($file[2])) {
                        $file[2] = '10';
                    }

                    $arrayFile['order'] = str_pad($file[2], 5, "0", STR_PAD_LEFT);
                    $files[] = $arrayFile;
                }
            }
            if (empty($files)) {
                return $files;
            }

            usort($files, function ($a, $b) {
                return strcmp($a['file'], $b['file']) // status ascending
                    ?: strcmp($a['position'], $b['position']) // start ascending
                        ?: strcmp($a['order'], $b['order']) // mh ascending
                    ;
            });

            return $files;
        });
    }

    private static function iconFunction(): TwigFunction
    {
        return new TwigFunction('icon', function (string $icon, string $title = '') {
            $title = empty($title) ? '' : ' title="' . $title . '"';
            return '<i class="' . $icon . '"' . $title . '></i>';
        });
    }

    /**
     * @throws LoaderError
     */
    private static function loadPluginFolders()
    {
        // Core namespace
        self::$loader->addPath(FS_FOLDER . '/Core/View', 'Core');

        // Plugin namespace
        $pluginManager = new PluginManager();
        foreach ($pluginManager->enabledPlugins() as $pluginName) {
            $pluginPath = FS_FOLDER . '/Plugins/' . $pluginName . '/View';
            if (file_exists($pluginPath)) {
                self::$loader->addPath($pluginPath, 'Plugin' . $pluginName);
                if (FS_DEBUG) {
                    self::$loader->prependPath($pluginPath);
                }
            }

            $pluginExtensionPath = FS_FOLDER . '/Plugins/' . $pluginName . '/Extension/View';
            if (file_exists($pluginExtensionPath)) {
                self::$loader->addPath($pluginExtensionPath, 'PluginExtension' . $pluginName);
                if (FS_DEBUG) {
                    self::$loader->prependPath($pluginExtensionPath);
                }
            }
        }
    }

    private static function moneyFunction(): TwigFunction
    {
        return new TwigFunction('money', function (float $number, string $currency = '') {
            if (empty($currency)) {
                return DivisaTools::format($number);
            }

            $divisa = Divisas::get($currency);
            if (false === $divisa->exists()) {
                return DivisaTools::format($number);
            }

            $divisaTools = new DivisaTools();
            $divisaTools->findDivisa($divisa);
            return DivisaTools::format($number);
        });
    }

    private static function settingsFunction(): TwigFunction
    {
        return new TwigFunction('settings', function (string $name, string $group = 'default') {
            return AppSettings::get($name, $group);
        });
    }

    private static function transFunction(): TwigFunction
    {
        return new TwigFunction('trans', function (string $txt, array $parameters = [], string $langCode = '') {
            $trans = new Translator();
            return empty($langCode) ?
                $trans->trans($txt, $parameters) :
                $trans->customTrans($langCode, $txt, $parameters);
        });
    }

    /**
     * @throws LoaderError
     */
    private static function twig(): Environment
    {
        if (false === defined('FS_DEBUG')) {
            define('FS_DEBUG', true);
        }

        // cargamos las rutas para las plantillas
        $path = FS_DEBUG ? FS_FOLDER . '/Core/View' : FS_FOLDER . '/Dinamic/View';
        self::$loader = new FilesystemLoader($path);
        if (self::$plugins) {
            self::loadPluginFolders();
        }
        foreach (self::$paths as $name => $customPath) {
            self::$loader->addPath($customPath, $name);
            if (FS_DEBUG) {
                self::$loader->prependPath($customPath);
            }
        }

        // cargamos las opciones de twig
        $options = ['debug' => FS_DEBUG];
        if (self::$plugins) {
            $options['cache'] = FS_FOLDER . '/MyFiles/Cache/Twig';
            $options['auto_reload'] = true;
        }
        self::$twig = new Environment(self::$loader, $options);

        // cargamos las funciones de twig
        self::$twig->addFunction(self::assetFunction());
        self::$twig->addFunction(self::attachedFileFunction());
        self::$twig->addFunction(self::formTokenFunction());
        self::$twig->addFunction(self::getIncludeViews());
        self::$twig->addFunction(self::iconFunction());
        self::$twig->addFunction(self::moneyFunction());
        self::$twig->addFunction(self::settingsFunction());
        self::$twig->addFunction(self::transFunction());
        foreach (self::$functions as $function) {
            self::$twig->addFunction($function);
        }

        return self::$twig;
    }
}
