<?php

/**
 * @package  mod_quicklog
 *
 * @copyright 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Brambring\Module\Quicklog\Administrator\Dispatcher;

use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Helper\HelperFactoryAwareInterface;
use Joomla\CMS\Helper\HelperFactoryAwareTrait;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\Language\Text;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Dispatcher class for mod_quicklog
 *
 * @since  4.4.0
 */
class Dispatcher extends AbstractModuleDispatcher implements HelperFactoryAwareInterface
{
    use HelperFactoryAwareTrait;

    /**
     * Returns the layout data.
     *
     * @return  array
     *
     * @since   4.4.0
     */


    /**
     * Returns the layout data. This function can be overridden by subclasses to add more
     * attributes for the layout.
     *
     * If false is returned, then it means that the dispatch process should be stopped.
     *
     * @return  array|false
     *
     * @since   4.0.0
     */

    protected function getLayoutData(): array|bool
    {


        $data     = parent::getLayoutData();
        $params   = $data['params'];


 
      

      //  $data['list']   = $this->getHelperFactory()->getHelper('QuicklogHelper')->getLogFiles();
        $data['access'] = $this->getHelperFactory()->getHelper('QuicklogHelper')->hasAccess();
       

        $cacheParams               = new \stdClass();
        $cacheParams->cachesuffix  = 'mod_quicklog';
        $cacheParams->cachemode    = 'id';
        $cacheParams->class        =  $this->getHelperFactory()->getHelper('QuicklogHelper');
        $cacheParams->method       = 'getLogFiles';
        $cacheParams->methodparams = $params;
        $cacheParams->modeparams   = md5(join(':', [$this->module->module, $this->module->id]));

        $data['list'] = ModuleHelper::moduleCache($this->module, $params, $cacheParams);
        return $data;
    }
}
