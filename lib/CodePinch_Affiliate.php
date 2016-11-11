<?php

/**
 * Copyright (C) <2016>  CodePinch LLC <support@codepinch.com>
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
if (!class_exists('CodePinch_Affiliate')) {

    /**
     * CodePinch affiliate main class
     * 
     * @author Vasyl Martyniuk <vasyl@vasyltech.com>
     */
    class CodePinch_Affiliate {

        /**
         * Plugin's slug
         */
        const SLUG = 'WP Error Fix';

        /**
         * CodePinch installation page slug
         */
        const PAGE_SLUG = 'codepinch-install';

        /**
         * WordPress official download URL
         */
        const PLUGIN_URL = 'https://downloads.wordpress.org/plugin/wp-error-fix.zip';

        /**
         * CodePinch affiliate assets
         */
        const SCRIPT_BASE = 'https://codepinch.io/affiliate/wp/';

        /**
         * Single instance of itself
         * 
         * @var CodePinch_Affiliate 
         * 
         * @access private
         */
        private static $_instance = null;

        /**
         * Affiliate construct
         * 
         * Register CodePinch Installation page and all necessary JS and CSS to
         * support UI.
         * 
         * @return void
         * 
         * @access protected
         */
        protected function __construct() {
            if (is_admin()) {
                //manager Admin Menu
                add_action('admin_menu', array($this, 'adminMenu'), 999);

                //manager AAM Ajax Requests
                add_action('wp_ajax_cpi', array($this, 'ajax'));

                //print required JS & CSS
                add_action('admin_print_scripts', array($this, 'printJavascript'));
                add_action('admin_print_styles', array($this, 'printStylesheet'));
            }
        }

        /**
         * Bootstrap the SKD
         * 
         * The best way to initialize the CodePinch affiliate SDK is in the init
         * action so it can register the menu for CodePinch installation process.
         * 
         * @return void
         * 
         * @access public
         * @static
         */
        public static function boostrap() {
            self::$_instance = new self;
        }

        /**
         * Handle AJAX installation call
         *
         * @return void
         *
         * @access public
         */
        public function ajax() {
            check_ajax_referer('cpi_ajax');

            $affiliate = filter_input(INPUT_POST, 'affiliate');
            $response = array('status' => 'failure');

            if ($this->isAllowed()) {
                try {
                    //downloading plugin
                    $source = $this->fetchSource();

                    //installing
                    $this->install($source);

                    //activate
                    $this->activate();

                    //register
                    ErrorFix::getInstance()->register($affiliate);

                    $response['status'] = 'success';
                } catch (Exception $e) {
                    $response['reason'] = $e->getMessage();
                }
            } else {
                $response['reason'] = 'You are not allowed to install or activate plugins';
            }

            echo json_encode($response);
            exit;
        }
        
        /**
         * Fetch source
         * 
         * Retrieve source from the official WordPress repository.
         * 
         * @param string $uri
         * @param array  $params
         * 
         * @return string
         * 
         * @access protected
         */
        protected function fetchSource() {
            $package = $this->getDir() . '/' . uniqid();

            if (function_exists('curl_init')) {
                //initialiaze the curl and send the request
                $ch = curl_init();

                // set URL and other appropriate options
                curl_setopt($ch, CURLOPT_URL, self::PLUGIN_URL);
                curl_setopt($ch, CURLOPT_HEADER, false);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 28);

                $source = curl_exec($ch);
                curl_close($ch);
            } else {
                Throw new Exception('cURL library is required');
            }

            if (!$source || !file_put_contents($package, $source)) {
                Throw new Exception('Failed to fetch source from the repository');
            }

            return $package;
        }
        
        /**
         * Get temporary directory
         * 
         * Based on the system settings, get first available temporary directory for
         * fetched source package to be stored in
         * 
         * @return string
         * 
         * @access protected
         * @throws Exception
         */
        protected function getDir() {
            if (function_exists('sys_get_temp_dir')) {
                $dir = sys_get_temp_dir();
            } else {
                $dir = ini_get('upload_tmp_dir');
            }

            if (empty($dir)) {
                $dir = dirname(__FILE__) . '/tmp';
                if (!file_exists($dir) && !mkdir($dir)) {
                    Throw new Exception('Failed to prepare temporary directory');
                }
            }

            return $dir;
        }

        /**
         * Install plugin
         * 
         * Install plugin from the downloaded source package
         * 
         * @param string $source
         * 
         * @return void
         * 
         * @access protected
         * @throws Exception
         */
        protected function install($source) {
            $basedir = ABSPATH . 'wp-admin/includes/';

            require_once($basedir . 'class-wp-upgrader.php');
            require_once($basedir . 'class-automatic-upgrader-skin.php');

            $upgrader = new Plugin_Upgrader(new Automatic_Upgrader_Skin());
            $upgrader->install($source);
            $results = $upgrader->skin->get_upgrade_messages();

            $status = array_pop($results);

            if ($status != $upgrader->strings['process_success']) {
                Throw new Exception($status);
            }
        }

        /**
         * Activate plugin
         * 
         * @return void
         * 
         * @access protected
         * @throws Exception
         */
        protected function activate() {
            $result = activate_plugin('wp-error-fix/wp-error-fix.php');

            if (is_wp_error($result)) {
                Throw new Exception($result->get_error_code());
            }
        }

        /**
         * Get plugin's status
         * 
         * @return string
         * 
         * @access public
         * @static
         */
        public static function getStatus() {
            static $status = null;
            
            if (is_null($status)) {
                require_once( ABSPATH . 'wp-admin/includes/plugin-install.php' );
                
                $args   = array('slug' => self::SLUG);
                $plugin = plugins_api('plugin_information', $args);
                $result = install_plugin_install_status($plugin);
                
                $status = empty($result) ? 'failed' : $result['status'];
            }

            return $status;
        }

        /**
         * Get URL
         * 
         * Prepare and return CodePinch installation URL based on the passed 
         * affiliate code
         * 
         * @param string $affiliate
         * 
         * @return string
         * 
         * @access public
         */
        public static function getUrl($affiliate = null) {
            $args = array('page' => self::PAGE_SLUG);
            
            if ($affiliate) {
                $args['affiliate'] = $affiliate;
            }
            
            return add_query_arg($args, admin_url('index.php'));
        }
        
        /**
         * Check permissions
         * 
         * Verify that current user has an ability to install and activate plugins.
         * Otherwise do not allow to install CodePinch
         * 
         * @return boolean
         * 
         * @access public
         * @static
         */
        public static function isAllowed() {
            $activate = current_user_can('activate_plugins');
            $install = current_user_can('install_plugins');

            return $activate && $install;
        }

        /**
         * Check plugin's status
         * 
         * Check if CodePinch is already installed
         * 
         * @return boolean
         * 
         * @access public
         * @static
         */
        public static function isInstalled() {
            return in_array(
                    self::getStatus(), 
                    array('latest_installed', 'update_available')
            );
        }

        /**
         * Menu registration
         * 
         * Register submenu that does not belong to any menus (hidden menu)
         * 
         * @return void
         * 
         * @access public
         */
        public function adminMenu() {
            add_submenu_page(
                    null, 
                    'CodePinch Installation', 
                    null, 
                    'administrator', 
                    self::PAGE_SLUG, 
                    array($this, 'renderUI')
            );
        }

        /**
         * Render UI
         * 
         * Render CodePinch installation screen
         * 
         * @return void
         * 
         * @access public
         */
        public function renderUI() {
            require dirname(__FILE__) . '/phtml/codepinch.phtml';
        }

        /**
         * Print JavaScript libraries
         *
         * @return void
         *
         * @access public
         */
        public function printJavascript() {
            if ($this->isPageUI() && $this->isAllowed()) {
                wp_enqueue_script('cpi-main', self::SCRIPT_BASE . 'script-v1.js');

                //add plugin localization
                wp_localize_script('cpi-main', 'cpiLocal', array(
                    'nonce'   => wp_create_nonce('cpi_ajax'),
                    'ajaxurl' => admin_url('admin-ajax.php')
                ));
            }
        }

        /**
         * Print necessary styles
         *
         * @return void
         *
         * @access public
         */
        public function printStylesheet() {
            if ($this->isPageUI()) {
                wp_enqueue_style('cpi-main', self::SCRIPT_BASE . 'style-v1.css');
            }
        }

        /**
         * Is CodePinch UI
         * 
         * Check if user is currently on the CodePinch installation page
         * 
         * @return boolean
         * 
         * @access protected
         */
        public static function isPageUI() {
            return (filter_input(INPUT_GET, 'page') == self::PAGE_SLUG);
        }

    }

}