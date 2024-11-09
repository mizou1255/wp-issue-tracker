<?php

namespace WPIssueTracker\Includes;

use WPIssueTracker\Includes\API\WPIssueTracker_Routes;

class WPIssueTracker_App
{
    protected $routes;

    public function __construct()
    {
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_menu', [$this, 'add_menu_page']);

        $this->routes = new WPIssueTracker_Routes;
        $this->register_api_routes();
    }

    private function register_api_routes()
    {
        $this->routes->add_route('/tickets', 'GET', $this, 'wp_wpit_get_tickets', function () {
            return true;
        });
        $this->routes->add_route('/create-ticket', 'POST', $this, 'wp_wpit_create_ticket', function () {
            return current_user_can('edit_posts');
        });
        $this->routes->add_route('/update-ticket-status/(?P<ticket_id>\d+)', 'POST', $this, 'update_ticket_status', function () {
            return current_user_can('edit_posts');
        });
        $this->routes->add_route('/get-ticket-details/(?P<ticket_id>\d+)', 'GET', $this, 'get_ticket_details', function () {
            return true;
        });
        $this->routes->add_route('/get-ticket-comments/(?P<ticket_id>\d+)', 'GET', $this, 'get_ticket_comments', function () {
            return true;
        });
        $this->routes->add_route('/add-comment', 'POST', $this, 'add_ticket_comment', function () {
            return true;
        });
        $this->routes->add_route('/priorities', 'GET', $this, 'get_priorities', function () {
            return true;
        });
        $this->routes->register_routes();
    }

    public function enqueue_scripts($hook)
    {
        $pages = array(
            'toplevel_page_wpissuetracker',
            'wpissuetracker_page_wpissuetracker-app-settings',
        );

        if (!in_array($hook, $pages)) {
            return;
        }

        wp_enqueue_script('wpissuetracker-admin-app', MYIT_URL . '/assets/dist/app.min.js', array(), MYIT_VERSION, true);
        wp_enqueue_style('wpissuetracker-app-css', MYIT_URL . '/assets/dist/app.min.css', array(), MYIT_VERSION);
        wp_enqueue_style('wpissuetracker-app-style', MYIT_URL . '/assets/dist/style.min.css', array(), MYIT_VERSION);
        wp_enqueue_style('wpissuetracker-notyf', MYIT_URL . '/assets/css/notyf.min.css', array(), MYIT_VERSION);
        wp_enqueue_style('fontawesome', MYIT_URL . '/assets/css/all.min.css', array(), MYIT_VERSION);
        add_filter('script_loader_tag', array($this, 'add_type_attribute'), 10, 2);

        require_once MYIT_PATH . '/languages/translations.php';
        wp_localize_script('wpissuetracker-admin-app', 'WPIT_Admin',
            array(
                'nonce' => wp_create_nonce('wp_rest'),
                'WPIT_trans' => $translations,
            )
        );
    }

    public function add_type_attribute($tag, $handle)
    {
        $scripts = array(
            'wpissuetracker-admin-app',
            'wpissuetracker-admin-settings',
        );

        if (in_array($handle, $scripts)) {
            return str_replace(' src', ' type="module" src', $tag);
        }

        return $tag;
    }

    public function add_menu_page()
    {
        add_menu_page(
            __('WPIssueTracker', 'wpissuetracker'),
            __('WPIssueTracker', 'wpissuetracker'),
            'manage_options',
            'wpissuetracker',
            array($this, 'admin_page'),
            MYIT_URL . '/assets/img/icon.png',
            23
        );
        add_submenu_page(
            'wpissuetracker',
            __('Dashboard', 'wpissuetracker'),
            __('Dashboard', 'wpissuetracker'),
            'manage_options',
            'wpissuetracker',
            array($this, 'admin_page'),
            1
        );
    }

    public function admin_page()
    {
        echo '<div id="wpissuetracker-admin-app"></div>';
    }

    public function wp_wpit_get_tickets(\WP_REST_Request $request)
    {
        global $wpdb;

        $page = isset($request['page']) ? absint($request['page']) : 1;
        $per_page = isset($request['per_page']) ? absint($request['per_page']) : 10;
        $offset = ($page - 1) * $per_page;

        $tickets = $wpdb->get_results($wpdb->prepare("
            SELECT t.*, p.name AS priority_name, u.display_name AS user_display_name
            FROM %i t
            LEFT JOIN %i p ON t.priority = p.id
            LEFT JOIN %i u ON t.user_id = u.ID
            ORDER BY t.created_at DESC
            LIMIT %d OFFSET %d", MYIT_TICKETS, MYIT_PRIORITIES, MYIT_USERS, $per_page, $offset), ARRAY_A);

        $total_tickets = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM %i", MYIT_TICKETS));
        $total_pages = ceil($total_tickets / $per_page);

        $status_counts = $wpdb->get_results($wpdb->prepare("
            SELECT status, COUNT(*) as count
            FROM %i
            GROUP BY status", MYIT_TICKETS), OBJECT_K);

        $statuses = [
            'new' => isset($status_counts['new']) ? $status_counts['new']->count : 0,
            'waiting' => isset($status_counts['waiting']) ? $status_counts['waiting']->count : 0,
            'in_progress' => isset($status_counts['in_progress']) ? $status_counts['in_progress']->count : 0,
            'resolved' => isset($status_counts['resolved']) ? $status_counts['resolved']->count : 0,
            'closed' => isset($status_counts['closed']) ? $status_counts['closed']->count : 0,
        ];

        $response = [
            'tickets' => $tickets,
            'total_tickets' => $total_tickets,
            'status_counts' => $statuses,
            'current_page' => $page,
            'per_page' => $per_page,
            'total_pages' => $total_pages,
        ];

        return new \WP_REST_Response($response, 200);
    }

    public function wp_wpit_create_ticket(\WP_REST_Request $request)
    {
        global $wpdb;

        $nonce = $request->get_header('X-WP-Nonce');
        if (!wp_verify_nonce($nonce, 'wp_rest')) {
            return new \WP_REST_Response(array('error' => __('Invalid nonce.', 'wpissuetracker')), 403);
        }

        if (!current_user_can('edit_posts')) {
            return new \WP_REST_Response(array('error' => __('Insufficient permissions.', 'wpissuetracker')), 403);
        }

        $title = sanitize_text_field($request->get_param('title'));
        $description = wp_kses_post($request->get_param('content'));
        $priority = intval($request->get_param('priority'));
        $user_id = get_current_user_id();

        $priority_name = $wpdb->get_var(
            $wpdb->prepare("SELECT name FROM %i WHERE id = %d", MYIT_PRIORITIES, $priority)
        );

        if (empty($title) || empty($description)) {
            return new \WP_REST_Response(array('error' => __('Title and description are required.', 'wpissuetracker')), 400);
        }

        $general_settings = get_option('wpit_general_settings', []);
        $default_status = isset($general_settings['defaultStatus']) ? $general_settings['defaultStatus'] : 'new';

        $wpdb->insert(
            MYIT_TICKETS,
            array(
                'title' => $title,
                'description' => $description,
                'priority' => $priority,
                'status' => $default_status,
                'user_id' => $user_id,
            ),
            array('%s', '%s', '%d', '%s', '%d')
        );

        $ticket_id = $wpdb->insert_id;

        if (!empty($_FILES['file'])) {
            if (!function_exists('wp_handle_upload')) {
                require_once ABSPATH . 'wp-admin/includes/file.php';
            }

            add_filter('upload_dir', array($this, 'custom_upload_dir'));

            add_filter('wp_handle_upload_prefilter', function ($file) use ($ticket_id) {
                $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
                $file['name'] = 'ticket_' . $ticket_id . '.' . $file_extension;
                return $file;
            });

            $upload = wp_handle_upload($_FILES['file'], array('test_form' => false));

            remove_filter('upload_dir', array($this, 'custom_upload_dir'));
            remove_filter('wp_handle_upload_prefilter', '__return_false');

            if ($upload && !isset($upload['error'])) {
                $attachment_url = esc_url_raw($upload['url']);
                $wpdb->update(
                    MYIT_TICKETS,
                    array('attachment_url' => $attachment_url),
                    array('id' => $ticket_id),
                    array('%s'),
                    array('%d')
                );

                if ($wpdb->last_error) {
                    return new \WP_Error(
                        'db_error',
                        __('Database error when inserting attachment:', 'wpissuetracker') . $wpdb->last_error,
                        array('status' => 500)
                    );
                }
            } else {
                return new \WP_REST_Response(array(
                    'error' => __('File upload failed:', 'wpissuetracker') . $upload['error'],
                ), 500);
            }
        }

        return new \WP_REST_Response(array('success' => true, 'ticket' => array(
            'id' => $ticket_id,
            'title' => $title,
            'description' => $description,
            'author' => get_the_author_meta('display_name', $user_id),
            'priority' => $priority,
            'priority_name' => $priority_name,
            'attachment_url' => isset($attachment_url) ? $attachment_url : null,
        )), 200);
    }

    public function update_ticket_status(\WP_REST_Request $request)
    {
        global $wpdb;
        $ticket_id = sanitize_text_field($request->get_param('ticket_id'));
        $new_status = sanitize_text_field($request->get_param('status'));

        if (!$ticket_id || !$new_status) {
            return new \WP_REST_Response(array('error' => __('Missing parameters', 'wpissuetracker')), 400);
        }

        $updated = $wpdb->update(
            MYIT_TICKETS,
            array('status' => $new_status),
            array('id' => $ticket_id),
            array('%s'),
            array('%d')
        );

        if ($updated === false) {
            return new \WP_REST_Response(array('error' => __('Error updating ticket status', 'wpissuetracker')), 500);
        }

        return new \WP_REST_Response(array('success' => true), 200);
    }

    public function get_ticket_details(\WP_REST_Request $request)
    {
        global $wpdb;
        $ticket_id = sanitize_text_field($request->get_param('ticket_id'));
        if (!$ticket_id) {
            return new \WP_REST_Response(array('error' => __('Ticket not found', 'wpissuetracker')), 404);
        }

        $details = $wpdb->get_row($wpdb->prepare("
            SELECT t.*, p.name AS priority_name
            FROM %i t
            LEFT JOIN %i p ON t.priority = p.id
            WHERE t.id = %d
            ORDER BY t.created_at DESC
        ", MYIT_TICKETS, MYIT_PRIORITIES, $ticket_id));

        if (empty($details)) {
            return new \WP_REST_Response(array('message' => __('No details found', 'wpissuetracker')), 200);
        }

        return new \WP_REST_Response(array(
            'success' => true,
            'id' => $details->id,
            'title' => $details->title,
            'description' => $details->description,
            'priority' => $details->priority,
            'priority_name' => $details->priority_name,
            'status' => $details->status,
            'attachment_url' => $details->attachment_url,
            'author' => get_userdata($details->user_id)->display_name,
            'date' => gmdate('d/m/Y H:i', strtotime($details->created_at)),
        ), 200);
    }

    public function get_ticket_comments(\WP_REST_Request $request)
    {
        global $wpdb;
        $ticket_id = sanitize_text_field($request->get_param('ticket_id'));
        if (!$ticket_id) {
            return new \WP_REST_Response(array('error' => __('Ticket not found', 'wpissuetracker')), 404);
        }

        $comments = $wpdb->get_results($wpdb->prepare("SELECT * FROM %i WHERE ticket_id = %d ORDER BY created_at DESC", MYIT_COMMENTS, $ticket_id));

        if (empty($comments)) {
            return new \WP_REST_Response(array('success' => true, 'message' => __('No comments found', 'wpissuetracker'), 'comments' => []), 200);
        }
        $formatted_comments = array_map(function ($comment) {
            return array(
                'id' => $comment->id,
                'comment' => $comment->comment,
                'author' => get_userdata($comment->user_id)->display_name,
                'date' => gmdate('d/m/Y H:i', strtotime($comment->created_at)),
            );
        }, $comments);

        return new \WP_REST_Response(array('success' => true, 'comments' => $formatted_comments), 200);
    }

    public function add_ticket_comment(\WP_REST_Request $request)
    {
        global $wpdb;

        $nonce = $request->get_header('X-WP-Nonce');
        if (!wp_verify_nonce($nonce, 'wp_rest')) {
            return new \WP_REST_Response(array('error' => __('Invalid nonce', 'wpissuetracker')), 403);
        }

        $ticket_id = sanitize_text_field($request->get_param('ticket_id'));
        $comment = sanitize_textarea_field($request->get_param('comment'));
        $user_id = get_current_user_id();

        if (empty($comment)) {
            return new \WP_REST_Response(array('error' => __('Comment cannot be empty', 'wpissuetracker')), 400);
        }

        $inserted = $wpdb->insert(
            MYIT_COMMENTS,
            array(
                'ticket_id' => $ticket_id,
                'user_id' => $user_id,
                'comment' => $comment,
                'created_at' => current_time('mysql'),
            ),
            array('%d', '%d', '%s', '%s')
        );

        if (!$inserted) {
            return new \WP_REST_Response(array('error' => __('Error adding comment', 'wpissuetracker')), 500);
        }

        return new \WP_REST_Response(array('success' => true, 'comment' => array('id' => $wpdb->insert_id, 'comment' => $comment, 'author' => wp_get_current_user()->display_name, 'date' => gmdate('d/m/Y H:i', time()))), 200);
    }

    public function get_priorities()
    {
        global $wpdb;

        $results = $wpdb->get_results($wpdb->prepare("SELECT id, name FROM %i", MYIT_PRIORITIES), ARRAY_A);

        return new \WP_REST_Response($results, 200);
    }

    public function custom_upload_dir($upload)
    {
        $upload_dir = WP_CONTENT_DIR . '/uploads/wpit-uploads';

        if (!file_exists($upload_dir)) {
            wp_mkdir_p($upload_dir);
        }

        $upload['path'] = $upload_dir;
        $upload['url'] = WP_CONTENT_URL . '/uploads/wpit-uploads';
        $upload['subdir'] = '';

        return $upload;
    }
}
