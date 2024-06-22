<?php

/**
 * @package    mod_easylog
 *
 * @copyright 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

declare(strict_types=1);

namespace Brambring\Module\Easylog\Administrator\Helper;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects

use Joomla\CMS\Date\Date;
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

    private ?Registry $params;
    /**
     * Returns a list of log files with metadata
     *
     * @return  array
     *
     * @since   4.4
     */

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
            $tz    = date_default_timezone_get();
            $mTime = new Date((string)$fileInfo->getMTime(), $tz);

            // date_default_timezone_get() is most likely the timezone of the file stamps
            $files[$fileInfo->getFileName()] = [
                'folder'    => $fileInfo->getPath(),
                'file'      => $fileInfo->getFileName(),
                'path'      => $fileInfo->getPathName(),
                'size'      => $this->humanFileSize($fileInfo->getSize()),
                'bytesSize' => $fileInfo->getSize(),
                'mtime'     => $fileInfo->getMTime(),
                'utc'       => HTMLHelper::_('date', $mTime, 'DATE_FORMAT_FILTER_DATETIME', 'UTC'),
                'date'      => HTMLHelper::_('date', $mTime, 'DATE_FORMAT_FILTER_DATETIME'),
            ];
        }
        uasort($files, [$this, 'rSortMTime']);

        $errorLog = \ini_get('error_log');

        if (is_readable($errorLog)) {
            $size               = filesize($errorLog);
            $mTime              = filemtime($errorLog);
            $files['error_log'] =
                [
                    'folder'    => \dirname($errorLog),
                    'file'      => basename($errorLog),
                    'path'      => $errorLog,
                    'size'      => $this->humanFileSize($size),
                    'bytesSize' => $size,
                    'mtime'     => $mTime,
                    'date'      => HTMLHelper::_('date', $mTime, 'DATE_FORMAT_FILTER_DATETIME', date_default_timezone_get()),
                ];
        }


        return $files;
    }

    /**
     * Has the current user administrator access
     *
     * @return  bool
     *
     * @since   4.4
     */

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

    /**
     * Return (part) of the requested log file
     *
     * A ajax call via com_ajax should return a value not null
     * howver this function closes the application
     *
     * @return  void
     *
     * @throws \Exception
     *
     * @since   4.4
     */

    public function viewAjax(): void
    {
        $app = Factory::getApplication();
        $this->hasAccess(true);
        $files = $this->getLogfiles();
        $input = $app->getInput();
        $name  = $input->get('name', '', 'cmd');
        if (isset($files[$name])) {
            $file = $files[$name];
            $id   = $input->get('id', 0, 'int');

            $maxSize  = 100;
            $maxLines = 100;
            $this->setParamsById((string)$id);

            $maxSize        = max(0, (int)($this->params?->get('maxSize') ?? $maxSize));
            $maxLines       = max(10, (int)($this->params?->get('maxLines') ?? $maxLines));
            $decorateLevels = $this->params?->get('decorateLevels', 1) ?? 1;

            $maxSize *= 1024;
            if ($maxSize !== 0 && $maxSize < $file['size']) {
                $readFile = false;
            } else {
                $readFile = true;
            }

            //tecnhically the better option would be to send it as text/plain
            //But how to anchor then to #bottom?


            print "<pre>";
            if ($readFile) {
                readfile($file['path']);
            } else {
                $string = $this->tailCustom($file['path'], $maxLines);
                if ($decorateLevels == 1) {
                    $this->echoCSS();
                    $string = $this->decorateLevels($string);
                }
                echo $string;
            }
            print "</pre>";
            print "<div name=\"bottom\" id=\"bottom\">File timestap: {$file['date']} (Local) - {$file['utc']} (UTC)</div>";
            $app->close();
        }

        throw new \Exception($app->getLanguage()->_('JGLOBAL_AUTH_ACCESS_DENIED'), 403);
    }

    /**
     * return the log file with download headers
     *
     * A ajax call via com_ajax should return a value not null
     * howver this function closes the application
     *
     * @return  void
     *
     * @throws \Exception
     *
     * @since   4.4
     */

    public function downloadAjax(): void
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

    /**
     * deletes a log file
     *
     * A ajax call via com_ajax should return a value not null
     * howver this function redirect before returning to com_ajax
     *
     * @return  void
     *
     * @throws \Exception
     *
     * @since   4.4
     */


    public function deleteAjax(): void
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

    /**
     * Helper function to sort the files on last modification time
     *
     * @return  int
     *
     *
     * @since   4.4
     */

    private function rSortMTime(array $a, array $b): int
    {
        return $b['mtime'] <=> $a['mtime'];
    }

    /**
     * get the params for a given module id.
     * The 'string' type of the $id is weird but consistient with getModuleById
     *
     * @return  void
     *
     * @throws \Exception
     *
     * @since   4.4
     */

    private function setParamsById(string $id): void
    {
        if (isset($this->params)) {
            return;
        }
        $module  = ModuleHelper::getModuleById($id);

        if ($module->id > 0) {
            $this->params = new Registry($module->params);
        } else {
            $app = Factory::getApplication();
            throw new \Exception($app->getLanguage()->_('JGLOBAL_AUTH_ACCESS_DENIED'), 403);
        }
    }
    /**
     * returns a string, convertion bytecount to a more readable string
     *
     * @return  string
     *
     *
     * @since   4.4
     */


    private function humanFileSize(int $filesize, int $precision = 0): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        $filesize = max($filesize, 0);
        $pow      = floor(($filesize ? log($filesize) : 0) / log(1024));
        $pow      = min($pow, \count($units) - 1);

        $filesize /= pow(1024, $pow);

        return round($filesize, $precision) . ' ' . $units[$pow];
    }
    private function getLogsPath(): string
    {

        return Factory::getApplication()->get('log_path');
    }

    /**
     * Returns the last $lines lines of a file
     * Coding rules applied from https://gist.github.com/lorenzos/1711e81a9162320fde20
     * Slightly modified version of http://www.geekality.net/2011/05/28/php-tail-tackling-large-files/
     * @author Torleif Berger, Lorenzo Stanco
     * @link http://stackoverflow.com/a/15025877/995958
     * @license   GNU General Public License version 3 or later;
     * @return  string
     *
     *
     * @since   4.4
     */
    private function tailCustom(string $filepath, int $lines = 1, bool $adaptive = true): string
    {

        // Open file
        $f = @fopen($filepath, "rb");
        if ($f === false) {
            return '';
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
        $chunk  = '';

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
    /**  Adds class around known log level strings
     * @return  string
     *
     *
     * @since   4.4
     */

    private function decorateLevels(string $string): string
    {
        return
            preg_replace('#\s(ALL|EMERGENCY|ALERT|CRITICAL|ERROR|WARNING|NOTICE|INFO|DEBUG)\s#', ' <span class="$1">$1</span> ', $string);
    }

    private function getCss(): string
    {

        return "
        pre {
        line-height: 2em;
    }
.EMERGENCY {
    background-color: rgb(228, 177, 84);
    background-color: orange;
    colr:#000;
    padding: 2px;
}
.ALERT {
    background-color: rgb(179, 81, 24);
    color: #fff;
    padding: 2px;
}
.CRITICAL {
    background-color: rgb(160, 95, 9);
    color: #fff;
    padding: 2px;
}
.ERROR {
    background-color: rgb(255, 0, 0);
    color: #fff;
    padding: 2px;
}
.WARNING {
    background-color: orange;
    colr:#000;
    padding: 2px;
}
.NOTICE {
    background-color: rgb(36, 75, 201);
    color: #fff;
    padding: 2px;
}
.INFO {
    background-color: rgb(18, 129, 173);
    color: #fff;
    padding: 2px;
}
.DEBUG {
    background-color: rgb(50, 102, 30);
    color: #fff;
    padding: 2px;
}";
    }

    private function echoCSS(): void
    {
        echo '<style>' . $this->getCss() . '</style>';
    }
}
