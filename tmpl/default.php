<?php

/**
 * @package  mod_quicklog
 *
 * @copyright 2024 Bram Brambring (https://brambring.nl)
 * @license   GNU General Public License version 3 or later;
 */

use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Toolbar\Button\LinkButton;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Uri\Uri;

// phpcs:disable PSR1.Files.SideEffects
\defined('_JEXEC') or die;
// phpcs:enable PSR1.Files.SideEffects


?>

<table class="table">
  <?php
      $query = [
        'option' => 'com_ajax',
        'module' => 'quicklog',
        'format' => 'raw',
        'method' => 'view',

        Session::getFormToken() => 1,
      ];
      foreach ($list as $name => $file) {
          //reset just to be sure

          $query['name']       = $name;
          $query['method']     = 'view';
          $toolbar             = new Toolbar($name);
          $url                 = Route::link('administrator', $query) . '#bottom';
          $viewButton          = new LinkButton('view-' . $name, 'View');
          $viewButton->url($url)
            // ->iframeWidth(640)
            //   ->iframeHeight(480)
            ->icon('icon-eye')
            ->target('view-source');
          $toolbar->appendButton($viewButton);

          $query['method'] = 'download';
          $url             = Route::link('administrator', $query);
          $downloadButton  = new LinkButton('download-' . $name, 'Download');
          $downloadButton->url($url)
            ->icon('icon-download');
          $toolbar->appendButton($downloadButton);


          $query['method'] = 'delete';
          $query['return'] = base64_encode(Uri::getInstance()->toString(['path','query']));
          $url             = Route::link('administrator', $query);
          $deleteButton    = new LinkButton('delete-' . $name, 'Delete',
            [
              'attributes'=>
              [
                'onclick'=>htmlspecialchars("return confirm('Are you sure you want to delete? Confirming will permanently delete the file $name!')")
                ]
              ]
            );
        
          $deleteButton->url($url)
            ->buttonClass('button-delete btn btn-danger btn-sm')
            ->icon('icon-delete')
            ->onclick('');
          $toolbar->appendButton($deleteButton);


          print "<tr><th scope=\"row\" class=\"border-0\"  colspan=\"3\">{$file['file']}</th></tr>";
          if ($access) {
              print "<tr><td class=\"border-0\" >" .   $viewButton->render() . "</td>
    <td class=\"border-0\" >" .   $downloadButton->render() . "</td>
    <td class=\"border-0\" >" .   $deleteButton->render() . "</td>
</tr>";
          }
          print "<tr><td >{$file['size']}</td><td  colspan=\"2\">{$file['date']}</td></tr>";
      }

        ?>
</table>