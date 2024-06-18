<?php

/**
 * @package  mod_quicklog
 *
 * @copyright 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

declare(strict_types=1);

namespace Brambring\Module\Quicklog\Administrator\Helper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Session\Session;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Filesystem\File;


class QuicklogHelper implements DatabaseAwareInterface
{
    use DatabaseAwareTrait;

    public function getLogfiles()
    {

        $logPath = $this->getLogsPath();
        $files   = [];
        //Folder:fiels does not include metadata
        foreach (new \DirectoryIterator($logPath) as $fileInfo) {
            if ($fileInfo->isDot()) {
                // never include dot files
                continue;
            }

            // folder
            if ($fileInfo->isDir()) {
                continue;
            }
            if ($fileInfo->getExtension() != 'php') {
                continue;
            }





            // date_default_timezone_get() is most likely the timezone of the file stamps
            $files[$fileInfo->getFileName()] = [
                'folder'    => $fileInfo->getPath(),
                'file'      => $fileInfo->getFileName(),
                'path'      => $fileInfo->getPathName(),
                'size'      => $this->humanFileSize($fileInfo->getSize()),
                'bytesSize' => $fileInfo->getSize(),
                'mtime'     => $fileInfo->getMTime(),
                'date'      => HTMLHelper::_('date', $fileInfo->getMTime(), 'DATE_FORMAT_FILTER_DATETIME', date_default_timezone_get()),
            ];
        }
        uasort($files, [$this, 'rSortMTime']);

        $errorLog = ini_get('error_log');
 
        if (is_readable($errorLog)) {
            $size = \filesize($errorLog);
            $mTime = \filemtime($errorLog);
            $files['error_log'] =
                [
                    'folder'    => \dirname($errorLog),
                    'file'      => \basename($errorLog),
                    'path'      => $errorLog,
                    'size'      => $this->humanFileSize($size),
                    'bytesSize' => $size,
                    'mtime'     => $mTime,
                    'date'      => HTMLHelper::_('date', $mTime, 'DATE_FORMAT_FILTER_DATETIME', date_default_timezone_get()),
                ];
        }

     
        return $files;
    }
    public function hasAccess(bool $throw = false): bool
    {
        $app  = Factory::getApplication();
        $user = $app->getIdentity();
        if (!$user->authorise('core.admin')) {
            if ($throw) {
                throw new \Exception($app->getLanguage()->_('JGLOBAL_AUTH_ACCESS_DENIED'), 403);
            }
            return false;
        }
        return true;
    }
    private function checkSession()
    {
        $app = Factory::getApplication();
        if (!Session::checkToken('get')) {
            throw new \Exception($app->getLanguage()->_('JGLOBAL_AUTH_ACCESS_DENIED'), 403);
        }
    }

    public function viewAjax()
    {

        $app = Factory::getApplication();
        $this->checkSession();
        $this->hasAccess(true);
        $files = $this->getLogfiles();
        $input = $app->getInput();
        $name  = $input->get('name', '', 'cmd');
        if (isset($files[$name])) {
            ob_start();
            print "<pre>";
            readfile($files[$name]['path']);
            print "</pre>";
            print '<div name="bottom" id="bottom">&nbsp;</div>';
            return ob_get_clean();
        }

        throw new \Exception($app->getLanguage()->_('JGLOBAL_AUTH_ACCESS_DENIED'), 403);
    }

    public function downloadAjax()
    {

        $app = Factory::getApplication();
        $this->checkSession();
        $this->hasAccess(true);
        $files = $this->getLogfiles();
        $input = $app->getInput();
        $name  = $input->get('name', '', 'cmd');
        if (isset($files[$name])) {
            $file = $files[$name];


            header("Content-type:application/x-httpd-php");
            header('Content-Disposition: attachment; filename="' . $name);
            header('Content-Transfer-Encoding: binary');

            header('Content-Length: ' . $file['size']);
            readfile($file['path']);
            $app->close();
        }
        throw new \Exception($app->getLanguage()->_('JGLOBAL_AUTH_ACCESS_DENIED'), 403);
    }


    public function deleteAjax()
    {
        $this->checkSession();
        $this->hasAccess(true);
        $app = Factory::getApplication();

        $files  = $this->getLogfiles();
        $input  = $app->getInput();
        $name   = $input->get('name', '', 'cmd');
        $return = base64_decode($input->get('return', '', 'string'));
        if (strpos($return, 'http') === 0) {
            //some using the link to redirect to a nasty place
            throw new \Exception($app->getLanguage()->_('JGLOBAL_AUTH_ACCESS_DENIED'), 403);
        }

        if ( $name == 'error_log') {
            $app->enqueueMessage("Sorry you can't delete this system file", $app::MSG_WARNING);
            $app->redirect($return);

        }

        if (isset($files[$name])) {
            ob_start();

            if (File::delete($files[$name]['path'])) {
                $app->enqueueMessage("Log file $name deleted");
            } else {
                $app->enqueueMessage("Deletion of Log file $name Failed", $app::MSG_ERROR);
            }
            $app->redirect($return);

            $app->close();
        }

        throw new \Exception($app->getLanguage()->_('JGLOBAL_AUTH_ACCESS_DENIED'), 403);
    }

    private function rSortMTime($a, $b)
    {
        return $b['mtime'] <=> $a['mtime'];
    }
    private function humanFileSize($filesize, $precision = 0)
    {
        $units = ['B', 'KB', 'MB', 'GB', 'GB'];

        $filesize = max($filesize, 0);
        $pow      = floor(($filesize ? log($filesize) : 0) / log(1024));
        $pow      = min($pow, \count($units) - 1);

        $filesize /= pow(1024, $pow);

        return round($filesize, $precision) . ' ' . $units[$pow];
    }
    private function getLogsPath()
    {

        return Factory::getApplication()->get('log_path');
    }
}
