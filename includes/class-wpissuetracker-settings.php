<?php

namespace WPIssueTracker\Includes;

use WPIssueTracker\Includes\API\WPIssueTracker_Routes;

class WPIssueTracker_Settings
{
    protected $routes;

    public function __construct()
    {
        add_action('admin_menu', array($this, 'add_submenu_page'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));

        $this->routes = new WPIssueTracker_Routes;
        $this->register_api_routes();
    }

    public function add_submenu_page()
    {
        add_submenu_page(
            'wpissuetracker',
            __('Settings', 'wpissuetracker'),
            __('Settings', 'wpissuetracker'),
            'manage_options',
            'wpissuetracker-app-settings',
            array($this, 'render_page'),
            10
        );
    }

    public function render_page()
    {
        echo '<div id="wpissuetracker-admin-settings"></div>';
    }

    public function enqueue_scripts($hook_suffix)
    {
        if ('wpissuetracker_page_wpissuetracker-app-settings' === $hook_suffix) {
            wp_enqueue_script('wpissuetracker-admin-settings', MYIT_URL . '/assets/dist/settings.min.js', array(), MYIT_VERSION, true);
        }
    }

    private function register_api_routes()
    {
        $this->routes->add_route('/settings', 'GET', $this, 'get_settings', function () {
            return current_user_can('manage_options');
        });
        $this->routes->add_route('/priorities', 'GET', $this, 'get_priorities', function () {
            return current_user_can('manage_options');
        });
        $this->routes->add_route('/priorities', 'POST', $this, 'add_priority', function () {
            return current_user_can('manage_options');
        });
        $this->routes->add_route('/priorities/(?P<id>\d+)', 'PUT', $this, 'update_priority', function () {
            return current_user_can('manage_options');
        });
        $this->routes->add_route('/priorities/(?P<id>\d+)', 'DELETE', $this, 'delete_priority', function () {
            return current_user_can('manage_options');
        });
        $this->routes->add_route('/roles-access', 'GET', $this, 'get_roles_access', function () {
            return current_user_can('manage_options');
        });
        $this->routes->add_route('/update-roles-access', 'POST', $this, 'update_roles_access', function () {
            return current_user_can('manage_options');
        });
        $this->routes->add_route('/settings/general', 'POST', $this, 'save_general_settings', function () {
            return current_user_can('manage_options');
        });
        $this->routes->add_route('/settings/email', 'POST', $this, 'save_email_settings', function () {
            return current_user_can('manage_options');
        });

        $this->routes->register_routes();
    }

    public function get_settings()
    {
        $general_settings = get_option('wpit_general_settings', [
            'ticketLimit' => 5,
            'defaultStatus' => 'new',
            'maintenanceMode' => false,
            'attachmentSizeLimit' => 5,
            'clientChangeStatus' => false,
        ]);

        $email_settings = get_option('wpit_email_settings', [
            'emailNotifications' => true,
            'useCustomEmail' => false,
            'notificationEmail' => '',
            'subject' => __('Ticket Notification', 'wpissuetracker'),
            'body' => __('Here are the details of your ticket.', 'wpissuetracker'),
            'adminNotifications' => true,
            'userNotifications' => true,
        ]);

        $settings = [
            'general' => $general_settings,
            'email' => $email_settings,
        ];

        return new \WP_REST_Response(['settings' => $settings], 200);
    }

    public function get_priorities()
    {
        global $wpdb;

        $priorities = $wpdb->get_results("SELECT * FROM %i", MYIT_PRIORITIES, ARRAY_A);

        if (!empty($priorities)) {
            return new \WP_REST_Response($priorities, 200);
        }

        return new \WP_REST_Response(array('message' => __('No priorities found', 'wpissuetracker')), 404);
    }

    public function add_priority(\WP_REST_Request $request)
    {
        if (!current_user_can('manage_options')) {
            return new \WP_REST_Response(['error' => __('You do not have permission to perform this action.', 'wpissuetracker')], 403);
        }

        global $wpdb;

        $name = sanitize_text_field($request->get_param('name'));

        if (empty($name)) {
            return new \WP_REST_Response(array('error' => __('Priority name is required', 'wpissuetracker')), 400);
        }

        $inserted = $wpdb->insert(
            MYIT_PRIORITIES,
            array('name' => $name),
            array('%s')
        );

        if ($inserted) {
            return new \WP_REST_Response(array('success' => true, 'message' => __('Priority added successfully', 'wpissuetracker'), 'id' => $wpdb->insert_id, 'name' => $name), 200);
        }

        return new \WP_REST_Response(array('error' => __('Error adding priority', 'wpissuetracker')), 500);
    }

    public function update_priority(\WP_REST_Request $request)
    {
        global $wpdb;
        $id = intval($request->get_param('id'));

        $name = sanitize_text_field($request->get_param('name'));

        if (empty($name)) {
            return new \WP_REST_Response(array('error' => __('Priority name is required', 'wpissuetracker')), 400);
        }

        $updated = $wpdb->update(
            MYIT_PRIORITIES,
            array('name' => $name),
            array('id' => $id),
            array('%s'),
            array('%d')
        );

        if ($updated !== false) {
            return new \WP_REST_Response(array('success' => true, 'message' => __('Priority updated successfully', 'wpissuetracker'), 'id' => $id, 'name' => $name), 200);
        }

        return new \WP_REST_Response(array('error' => __('Error updating priority', 'wpissuetracker')), 500);
    }

    public function delete_priority(\WP_REST_Request $request)
    {
        global $wpdb;
        $id = intval($request->get_param('id'));

        $deleted = $wpdb->delete(
            MYIT_PRIORITIES,
            array('id' => $id),
            array('%d')
        );

        if ($deleted) {
            return new \WP_REST_Response(array('success' => true, 'message' => __('Priority deleted successfully', 'wpissuetracker')), 200);
        }

        return new \WP_REST_Response(array('error' => __('Error deleting priority', 'wpissuetracker')), 500);
    }

    public function get_roles_access()
    {
        global $wp_roles;
        $roles = $wp_roles->roles;

        $allowed_roles = get_option('wpit_allowed_roles', []);

        $roles_access = [];
        foreach ($roles as $role_name => $role_info) {
            $roles_access[] = [
                'name' => $role_name,
                'label' => $role_info['name'],
                'hasAccess' => in_array($role_name, $allowed_roles),
            ];
        }

        return new \WP_REST_Response(['roles' => $roles_access], 200);
    }

    public function update_roles_access(\WP_REST_Request $request)
    {
        $roles = $request->get_param('roles');
        if (!is_array($roles)) {
            return new \WP_REST_Response(['error' => __('Invalid input', 'wpissuetracker')], 400);
        }
        update_option('wpit_allowed_roles', $roles);
        return new \WP_REST_Response(['success' => true], 200);
    }

    public function save_general_settings(\WP_REST_Request $request)
    {
        $general_settings = $request->get_json_params();
        update_option('wpit_general_settings', $general_settings);

        return new \WP_REST_Response(['success' => true, 'message' => __('General settings saved successfully', 'wpissuetracker')], 200);
    }

    public function save_email_settings(\WP_REST_Request $request)
    {
        $email_settings = $request->get_json_params();
        update_option('wpit_email_settings', $email_settings);

        return new \WP_REST_Response(['success' => true, 'message' => __('Email settings saved successfully', 'wpissuetracker')], 200);
    }
}
