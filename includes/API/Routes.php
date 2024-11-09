<?php

namespace WPIssueTracker\Includes\API;

class WPIssueTracker_Routes
{
    protected $routes = [];

    public function register_routes()
    {
        add_action('rest_api_init', function () {
            foreach ($this->routes as $route) {
                register_rest_route('wpissuetracker/v1', $route['route'], [
                    'methods' => $route['methods'],
                    'callback' => [$route['class'], $route['callback']],
                    'permission_callback' => $route['permission_callback'],
                ]);
            }
        });
    }

    public function add_route($route, $methods, $class, $callback, $permission_callback)
    {
        $this->routes[] = [
            'route' => $route,
            'methods' => $methods,
            'class' => $class,
            'callback' => $callback,
            'permission_callback' => $permission_callback,
        ];
    }
}
