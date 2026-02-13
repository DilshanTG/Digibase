<?php

namespace App\Http\Controllers;

class AdminerController extends Controller
{
    /**
     * Serve Adminer with auto-login for the current database.
     */
    public function index()
    {
        // 1. Mandatory Security Check - Ensure user is authenticated (admin)
        if (! auth()->check()) {
            abort(403, 'Unauthorized access to Database Manager.');
        }

        // 2. Define Adminer Autologin extension
        if (! function_exists('adminer_object')) {
            function adminer_object()
            {
                class AdminerAutologin extends \Adminer
                {
                    /**
                     * Bypass login form
                     */
                    public function login($login, $password)
                    {
                        return true;
                    }

                    /**
                     * Set database connection parameters
                     */
                    public function credentials()
                    {
                        return ['', '', ''];
                    }

                    /**
                     * Set the default database file for SQLite
                     */
                    public function database()
                    {
                        return config('database.connections.sqlite.database');
                    }

                    /**
                     * Disable the login form itself
                     */
                    public function loginForm()
                    {
                        return true;
                    }

                    /**
                     * Visual Improvements
                     */
                    public function name()
                    {
                        return 'Nexus DB Manager';
                    }

                    /**
                     * Dark mode support and styling
                     */
                    public function head()
                    {
                        echo <<<'HTML'
                            <style>
                                #menu { background: #1a1a1a; color: #fff; }
                                #content { background: #fdfdfd; }
                                h2 { color: #059669; }
                                a { color: #059669; text-decoration: none; }
                                a:hover { text-decoration: underline; }
                                .links { background: #f8fafc; border-bottom: 1px solid #e2e8f0; }
                            </style>
                        HTML;

                        return true;
                    }
                }

                return new AdminerAutologin;
            }
        }

        // 3. Force SQLite driver and database path via GET params
        $_GET['sqlite'] = '';
        $_GET['username'] = '';
        $_GET['db'] = config('database.connections.sqlite.database');

        // 4. Load Adminer
        $adminerPath = public_path('adminer-file.php');

        if (! file_exists($adminerPath)) {
            abort(404, 'Adminer binary not found.');
        }

        // Reset any output buffers
        if (ob_get_level()) {
            ob_end_clean();
        }

        require $adminerPath;
        exit;
    }
}
