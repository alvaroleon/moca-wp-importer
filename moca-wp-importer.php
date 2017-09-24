<?php

namespace EstudioMoca\Wordpress\DevTools;


/**
 * User: Estudio Moca
 * URL: https://www.estudiomoca.com
 * Author: Álvaro León
 * Date: 23/09/2017
 * Time: 23:29
 * Version: 0.1
 * Requires: PHP 5.6 or higher
 *
 * Description:
 * This script is for import posts from the other Wordpress installation db, including its meta data and attachment.
 *
 * Descripción:
 * Script para importar posts desde la base de datos, incluyendo sus meta datos y media, de otra instalación de Wordpress.
 *
 *
 * Notes:
 * It's not a Wordpress Plugin, it's only is a script.
 * This file has to be in the Wordpress root folder path of the new site.
 * This script not copy the "uploads" folder. Only extract data from the db of the old WP.
 * This script were tested in the 4.8 version of Wordpress.
 *
 *
 * Notas:
 * Este no es un plugin de Wordpress, es solo un script.
 * Este archivo tiene que ser puesto el la carpeta raíz de Wordpress del nuevo sitio.
 * Este script no copia la carpeta uploads. Solo extrae los datos de la base de datos.
 * Este script fue probado con la versión 4.8 de Wordpress.
 */

class WP_Importer
{
    // Wordpress Importer default params
    public
        $wp_new_path = 'C:\xampp\htdocs\chilecreativo.cl\web',
        $wp_old_path = 'C:\xampp\htdocs\chilecreativo.cl\web_old',
        $wp_old_url = 'http://local.chilecreativo.cl/web',
        $wp_new_url = 'http://local.chilecreativo.cl',
        $filter_by = 'post_title', // If the condition ($filter_by) is true, it's break the bucle (It's don't insert the post)" .
        $post_author_id = 2; // It's for WHERE SQL clausule, if it is null = nothing to do.

    public
        $post_type,
        $old_dbname,
        $old_dbuser,
        $old_dbpassword,
        $old_dbhost,
        $old_prefix,
        $old_wpdb,
        $new_wpdb;

    /**
     * WP_Importer constructor.
     * @param string $post_type
     */
    public function __construct($post_type = 'post')
    {
        global $wpdb;
        include $this->wp_new_path . DIRECTORY_SEPARATOR . 'wp-load.php';
        include $this->wp_new_path . DIRECTORY_SEPARATOR . 'wp-admin/includes/post.php';
        ini_set('display_errors', 1);

        $this->post_type = $post_type;
        $this->import_old_db_conn();
        $this->old_wpdb = new \wpdb($this->old_dbuser, $this->old_dbpassword, $this->old_dbname, $this->old_dbhost);
        $this->old_wpdb->set_prefix($this->old_prefix);
        $this->new_wpdb = $wpdb;
    }

    private function import_old_db_conn()
    {
        $file = fopen($this->wp_old_path . DIRECTORY_SEPARATOR . "wp-config.php", "r");

        while (!feof($file)) {
            $line = fgets($file);

            if (preg_match('/DB_NAME/', $line)) {
                preg_match("/define\('DB_NAME', '(.*)'\)/", $line, $matches);
                $this->old_dbname = $matches[1];
            }

            if (preg_match('/DB_USER/', $line)) {
                preg_match("/define\('DB_USER', '(.*)'\)/", $line, $matches);
                $this->old_dbuser = $matches[1];
            }

            if (preg_match('/DB_PASSWORD/', $line)) {
                preg_match("/define\('DB_PASSWORD', '(.*)'\)/", $line, $matches);
                $this->old_dbpassword = $matches[1];
            }

            if (preg_match('/DB_HOST/', $line)) {
                preg_match("/define\('DB_HOST', '(.*)'\)/", $line, $matches);
                $this->old_dbhost = $matches[1];
            }

            //echo $line;

            if (preg_match("/[\$]table_prefix/", $line)) {

                preg_match("/[\$]table_prefix[\s]*=[\s]*'(.*)'/", $line, $matches);
                $this->old_prefix = $matches[1];
            }
        }

        fclose($file);
    }

    public function import()
    {
        require_once(ABSPATH . 'wp-admin/includes/image.php');

        $old_posts = $this->old_wpdb->get_results("SELECT * FROM {$this->old_wpdb->posts} AS p 
                                      WHERE p.post_type = '{$this->post_type}' 
                                      AND (p.post_status = 'publish' OR p.post_status='private')");
        /**
         * @var \WP_Post $_post
         */
        foreach ($old_posts as $_post) {
            $old_thumb_id = null;
            $is_break = false;

            switch ($this->filter_by) {
                // You can to add more conditions
                case 'post_title':
                    if (\post_exists($_post->post_title)) $is_break = true;

                    break;
                default:
                    break;
            }

            if ($is_break) continue;

            $new_post_id = \wp_insert_post([
                'post_title' => $_post->post_title,
                'post_content' => $_post->post_content,
                'post_date' => $_post->post_date,
                'post_date_gmt' => $_post->post_date_gmt,
                'post_author' => $this->post_author_id,
                //'post_content_filtered' => $_post->post_content_filtered,
                'post_excerpt' => $_post->post_excerpt,
                'post_status' => $_post->post_status,
                'post_type' => $_post->post_type,
                //'comment_status' => $_post->comment_status,
                //'ping_status' => $_post->ping_status,
                //'post_password' => $_post->post_password,
                //'to_ping' => $_post->to_ping,
                'pinged' => $_post->pinged,
                //'post_parent' => $_post->post_parent,
                //'menu_order' => $_post->menu_order,
                //'guid' => $_post->guid,
                //'import_id' => $_post->import_id,
                //'context' => $_post->context,
            ]);

            if (!$new_post_id) continue;
            //print_r($new_post_id);
            //exit;

            /**
             * Add post meta data (including thumbnail).
             */
            $old_meta_list = $this->old_wpdb->get_results("SELECT * FROM {$this->old_wpdb->postmeta} AS pm 
                                      WHERE pm.post_id = {$_post->ID}
                                      AND (pm.meta_key NOT LIKE '%_edit%')");

            foreach ($old_meta_list as $_meta) {
                //$this->log($_meta, '$_meta', true);

                if ($_meta->meta_key == '_thumbnail_id') {
                    $old_thumb_id = $_meta->meta_value;
                    continue;
                } else {
                    continue;
                    \add_post_meta($new_post_id, $_meta->meta_key, $_meta->meta_value, true);

                    $this->log("POST ({$new_post_id}): {$_meta->meta_key}: {$_meta->meta_value}\n", 'MSG', true);
                }
            }

            /**
             * Add post thumbnail
             */
            if ($old_thumb_id) {
                $thumb = $this->old_wpdb->get_results("SELECT * FROM {$this->old_wpdb->posts} AS p 
                                      WHERE p.ID = {$old_thumb_id}")[0];

                if ($thumb) {
                    $this->log("POST ({$new_post_id}): Obtiene thumb\n", 'MSG', true);
                    //$this->log($thumb, '$thumb', true);
                    $thumb->guid = \str_replace($this->wp_old_url, $this->wp_new_url, $thumb->guid);
                    $filename = str_replace($this->wp_new_url, $this->wp_new_path, $thumb->guid);

                    $new_thumb_id = \wp_insert_attachment([
                        'guid' => $thumb->guid,
                        'post_mime_type' => $thumb->post_mime_type,
                        'post_title' => $thumb->post_title,
                        'post_content' => $thumb->post_content,
                    ], $filename, $new_post_id);

                    $this->log([
                        'guid' => $thumb->guid,
                        'post_mime_type' => $thumb->post_mime_type,
                        'post_title' => $thumb->post_title,
                        'post_content' => $thumb->post_content
                    ], 'args', true);

                    $this->log($new_thumb_id, '$new_thumb_id', true);

                    if ($new_thumb_id) {
                        $this->log("POST ({$new_post_id}): Inserta Attachment ($new_thumb_id)\n", 'MSG', true);
                        $attach_data = \wp_generate_attachment_metadata($new_thumb_id, $filename);
                        $this->log($attach_data, '$attach_data', true);

                        $attach_meta = \wp_update_attachment_metadata($new_thumb_id, $attach_data);
                        $is_thumb = \set_post_thumbnail($new_post_id, $new_thumb_id);
                        //$is_thumb = \add_post_meta($new_post_id, '_thumbnail_id', $new_thumb_id);
                        $this->log($attach_meta, '$attach_meta', true);
                        $this->log($is_thumb, '$is_thumb', true);

                        if ($is_thumb) {
                            $this->log("POST ({$new_post_id}): Agrega thumnnail ($new_thumb_id)\n", 'MSG', true);
                        } else {
                            $this->log("POST ({$new_post_id}): Salta thumnnail\n", 'MSG', true);
                        }
                    }

                }
            }

            $this->log("POST ({$new_post_id}) {$_post->post_title} - OK", 'MSG', true);
        }
    }

    /** Log function
     * @param $msg
     * @param string $prefix
     * @param bool $is_print
     */
    public function log($msg, $prefix = '', $is_print = false)
    {
        $str = date('[d-m-Y H:i:s] => ') . "\n";
        if ($prefix)
            $str .= "=== [{$prefix}] ===\n";

        $str .= print_r($msg, true) . "\n";

        if ($prefix)
            $str .= "=== [/{$prefix}] ===\n\n";

        //error_log($str, 3, $this->wp_new_path . DIRECTORY_SEPARATOR . 'log-wp-importer.txt');
        if ($is_print) echo $str;
    }
}


$importer = new WP_Importer();
$importer->import();
