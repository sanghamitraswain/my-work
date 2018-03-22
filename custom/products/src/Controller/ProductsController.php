<?php

    /**
     * @file
     * Contains \Drupal\products\Controller\ProductsController.
     */
    namespace Drupal\products\Controller;

    use Drupal\Core\Controller\ControllerBase;

    /**
     * Controller routines for products routes.
     */
    class ProductsController extends ControllerBase {

      /**
       * Callback function to get the data from REST API
       */
      public function getList() {
        print "response data";exit;
    }
}