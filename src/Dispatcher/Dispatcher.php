<?php

/**
 * @package    mod_easylog
 *
 * @copyright 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

namespace Brambring\Module\Easylog\Administrator\Dispatcher;

use Joomla\CMS\Dispatcher\AbstractModuleDispatcher;
use Joomla\CMS\Helper\HelperFactoryAwareInterface;
use Joomla\CMS\Helper\HelperFactoryAwareTrait;
use Joomla\CMS\Helper\ModuleHelper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

/**
 * Dispatcher class for mod_easylog
 *
 * @since  4.4
 */
class Dispatcher extends AbstractModuleDispatcher implements HelperFactoryAwareInterface
{
    use HelperFactoryAwareTrait;

    /**
     * Returns the layout data.
     *
     * @return  array
     *
     * @since   4.4
     */


    /**
     * Returns the layout data. This function can be overridden by subclasses to add more
     * attributes for the layout.
     *
     * If false is returned, then it means that the dispatch process should be stopped.
     *
     * @return  array|false
     *
     * @since   4.4
     */

    protected function getLayoutData(): array|bool
    {


        $data     = parent::getLayoutData();
        $params   = $data['params'];




        $helper = $this->getHelperFactory()->getHelper('EasylogHelper');
        //  $data['list']   = $this->getHelperFactory()->getHelper('EasylogHelper')->getLogFiles();
        $data['access'] = $helper->hasAccess();


        $cacheParams               = new \stdClass();
        $cacheParams->cachesuffix  = 'mod_easylog';
        $cacheParams->cachemode    = 'id';
        $cacheParams->class        = $helper;
        $cacheParams->method       = 'getLogFiles';
        $cacheParams->methodparams = $params;
        $cacheParams->modeparams   = md5(join(':', [$this->module->module, $this->module->id]));

        $data['list'] = ModuleHelper::moduleCache($this->module, $params, $cacheParams);
        return $data;
    }
}
