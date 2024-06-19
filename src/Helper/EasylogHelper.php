<?php

/**
 * @package  mod_easylog
 *
 * @copyright 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

declare(strict_types=1);

namespace Brambring\Module\Easylog\Administrator\Helper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ModuleHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\Database\DatabaseAwareInterface;
use Joomla\Database\DatabaseAwareTrait;
use Joomla\Filesystem\File;
use Joomla\Registry\Registry;

class EasylogHelper implements DatabaseAwareInterface
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


    public function viewAjax()
    {

        $app = Factory::getApplication();


        $this->hasAccess(true);
        $files = $this->getLogfiles();
        $input = $app->getInput();
        $name  = $input->get('name', '', 'cmd');
        if (isset($files[$name])) {
            $id  = $input->get('id', 0, 'int');

            $module  = ModuleHelper::getModuleById((string)$id);
            $maxSize = 100;
            $maxLines = 200;
          
            if ($module->id > 0) {
                $params = new Registry($module->params);
                $maxSize = max(0, (int)($params->get('maxSize') ?? $maxSize));
                $maxLines = max(10, (int)($params->get('maxLines') ?? $maxLines));
            }
            $maxSize *=1024;
            if ($maxSize !== 0 && $maxSize < $files[$name]['size']) {
                $readFile = false;
            } else {
                $readFile = true;
            }
       
            //tecnhically the better option would be to send it as text/plain
            //But how to anchor then to #bottom?


            print "<pre>";
            if ($readFile) {
                readfile($files[$name]['path']);
            } else {
                echo $this->tailCustom($files[$name]['path'], $maxLines);
            }
            print "</pre>";
            print '<div name="bottom" id="bottom">&nbsp;</div>';
            $app->close();
        }

        throw new \Exception($app->getLanguage()->_('JGLOBAL_AUTH_ACCESS_DENIED'), 403);
    }

    public function downloadAjax()
    {

        $app = Factory::getApplication();

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

        if ($name == 'error_log') {
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

    /**
     * https://gist.github.com/lorenzos/1711e81a9162320fde20
     * Slightly modified version of http://www.geekality.net/2011/05/28/php-tail-tackling-large-files/
     * @author Torleif Berger, Lorenzo Stanco
     * @link http://stackoverflow.com/a/15025877/995958
     * @license http://creativecommons.org/licenses/by/3.0/
     */
    private function tailCustom($filepath, $lines = 1, $adaptive = true)
    {

        // Open file
        $f = @fopen($filepath, "rb");
        if ($f === false) {
            return false;
        }

        // Sets buffer size, according to the number of lines to retrieve.
        // This gives a performance boost when reading a few lines from the file.
        if (!$adaptive) {
            $buffer = 4096;
        } else {
            $buffer = ($lines < 2 ? 64 : ($lines < 10 ? 512 : 4096));
        }

        // Jump to last character
        fseek($f, -1, SEEK_END);

        // Read it and adjust line number if necessary
        // (Otherwise the result would be wrong if file doesn't end with a blank line)
        if (fread($f, 1) != "\n") {
            $lines -= 1;
        }

        // Start reading
        $output = '';
        $chunk = '';

        // While we would like more
        while (ftell($f) > 0 && $lines >= 0) {

            // Figure out how far back we should jump
            $seek = min(ftell($f), $buffer);

            // Do the jump (backwards, relative to where we are)
            fseek($f, -$seek, SEEK_CUR);

            // Read a chunk and prepend it to our output
            $output = ($chunk = fread($f, $seek)) . $output;

            // Jump back to where we started reading
            fseek($f, -mb_strlen($chunk, '8bit'), SEEK_CUR);

            // Decrease our line counter
            $lines -= substr_count($chunk, "\n");
        }

        // While we have too many lines
        // (Because of buffer size we might have read too many)
        while ($lines++ < 0) {

            // Find first newline and remove all text before that
            $output = substr($output, strpos($output, "\n") + 1);
        }

        // Close file and return
        fclose($f);
        return trim($output);
    }
}
