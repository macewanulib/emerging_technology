<?php

namespace Drupal\emerging_technology\Controller;

use Drupal\Core\Controller\ControllerBase;

class ETPrintController extends ControllerBase {

  public function content() {
    $page_html = '<h2>3D Print Requests</h2><p>Due to machine repair and other factors, we are currently unable to accept any new 3D print job requests until the new year. The request form will be available again starting January 7th. If you have an urgent request or inquiry please email <a href="mailto:service_email@yourinstitution.com"</a>service_email@yourinstitution.com.</p>';
    $build = [
      '#type' => 'markup',
      '#title' => '3D Print Requests',
      '#markup' => $page_html,
    ];
    return $build;
  }

}
