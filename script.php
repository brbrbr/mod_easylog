<?php

/**
 * @package     mod_easylog
 * @version        24.44
 * @copyright 2023 - 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 **/

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Application\AdministratorApplication;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Installer\InstallerScriptInterface;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Joomla\Database\DatabaseDriver;
use Joomla\Database\DatabaseInterface;

// phpcs:disable PSR12.Classes.AnonClassDeclaration
return new class() implements
    ServiceProviderInterface
{
    // phpcs:enable PSR12.Classes.AnonClassDeclaration
    public function register(Container $container)
    {
        $container->set(
            InstallerScriptInterface::class,
            // phpcs:disable PSR12.Classes.AnonClassDeclaration
            new class($container->get(AdministratorApplication::class)) implements
                InstallerScriptInterface
            {
                // phpcs:enable PSR12.Classes.AnonClassDeclaration
                protected AdministratorApplication $app;
                private string $minimumJoomlaVersion = '4.4';
                // phpcs:enable PSR12.Classes.AnonClassDeclaration

                protected DatabaseDriver $db;


                public function __construct(AdministratorApplication $app)
                {
                    $this->app = $app;
                    $this->db  = Factory::getContainer()->get(DatabaseInterface::class);
                }

                public function preflight($type, InstallerAdapter $adapter): bool
                {
                    if ($type == 'uninstall') {
                        return true;
                    }

                    if (version_compare(JVERSION, $this->minimumJoomlaVersion, '<')) {
                        Log::add(
                            Text::sprintf('JLIB_INSTALLER_MINIMUM_JOOMLA', $this->minimumJoomlaVersion),
                            Log::ERROR,
                            'jerror'
                        );
                        return false;
                    }
                    return true;
                }
                public function install(InstallerAdapter $adapter): bool
                {
                    //with a normal installation  joomla ads a blank module unpublished and with __modules_menu
                    //doesn't when it's a discover install. - for now let's ignore that.

                    $query = $this->db->getQuery(true);
                    //in general there should be a module added
                    $query->from($this->db->quoteName('#__modules'))
                        ->select('id')
                        ->where($this->db->quoteName('module') . ' = ' . $this->db->quote('mod_easylog'))
                        ->where($this->db->quoteName('client_id') . ' = 1')
                        ->where($this->db->quoteName('published') . ' = 0'); //<-- new module check 1
                    $moduleId = $this->db->setQuery($query)->loadResult();

                    if (empty($moduleId)) {
                        return true;
                    }

                  
                    $this->db->setQuery($query);
                    $exists = $this->db->loadResult();

                    if ($exists) {
                        return true;
                    }

                    $module = (object) [
                        'title'     => 'Log Viewer',
                        'position'  => 'cpanel-system',
                        'published' => 1,
                        'showtitle' => 1,
                        'params'    => '{"maxSize":"10","maxLines":"20","module_tag":"div","bootstrap_size":"0","header_tag":"h3","header_class":"","style":"0"}',
                        //    'menu_assignment' => '{"assigned":[],"assignment":0}', //Joomla 5.2
                        'id' => $moduleId,
                    ];
                    $this->db->updateObject('#__modules', $module, 'id', false);

            
                    return true;
                }

                public function update(InstallerAdapter $adapter): bool
                {
                    return true;
                }

                public function uninstall(InstallerAdapter $adapter): bool
                {
                    return true;
                }

                public function postflight(string $type, InstallerAdapter $adapter): bool
                {
                    return true;
                }
            }
        );
    }
};
