<?php

namespace Formwork\Admin\Controllers;

use Formwork\Admin\Admin;
use Formwork\Admin\Security\AccessLimiter;
use Formwork\Admin\Security\CSRFToken;
use Formwork\Admin\Utils\Session;
use Formwork\Data\DataGetter;
use Formwork\Utils\HTTPRequest;

class Authentication extends AbstractController
{
    /**
     * Authentication@login action
     */
    public function login(): void
    {
        $limiter = new AccessLimiter(
            $this->registry('accessAttempts'),
            $this->option('admin.login_attempts'),
            $this->option('admin.login_reset_time')
        );

        if ($limiter->hasReachedLimit()) {
            $minutes = round($this->option('admin.login_reset_time') / 60);
            $this->error($this->label('login.attempt.too-many', $minutes));
            return;
        }

        switch (HTTPRequest::method()) {
            case 'GET':
                if (Session::has('FORMWORK_USERNAME')) {
                    $this->redirectToPanel();
                }

                // Always generate a new CSRF token
                CSRFToken::generate();

                $this->view('authentication.login', [
                    'title' => $this->label('login.login')
                ]);

                break;

            case 'POST':
                // Delay request processing for 0.5-1s
                usleep(random_int(500, 1000) * 1e3);

                $data = new DataGetter(HTTPRequest::postData());

                // Ensure no required data is missing
                if (!$data->hasMultiple(['username', 'password'])) {
                    $this->error($this->label('login.attempt.failed'));
                }

                $limiter->registerAttempt();

                $user = Admin::instance()->users()->get($data->get('username'));

                // Authenticate user
                if ($user !== null && $user->authenticate($data->get('password'))) {
                    Session::set('FORMWORK_USERNAME', $data->get('username'));

                    // Regenerate CSRF token
                    CSRFToken::generate();

                    $time = $this->log('access')->log($data->get('username'));
                    $this->registry('lastAccess')->set($data->get('username'), $time);

                    $limiter->resetAttempts();

                    if (($destination = Session::get('FORMWORK_REDIRECT_TO')) !== null) {
                        Session::remove('FORMWORK_REDIRECT_TO');
                        $this->redirect($destination);
                    }

                    $this->redirectToPanel();
                }

                $this->error($this->label('login.attempt.failed'), [
                    'username' => $data->get('username'),
                    'error'    => true
                ]);

                break;
        }
    }

    /**
     * Authentication@logout action
     */
    public function logout(): void
    {
        CSRFToken::destroy();
        Session::remove('FORMWORK_USERNAME');
        Session::destroy();

        if ($this->option('admin.logout_redirect') === 'home') {
            $this->redirectToSite();
        } else {
            $this->notify($this->label('login.logged-out'), 'info');
            $this->redirectToPanel();
        }
    }

    /**
     * Display login view with an error notification
     *
     * @param string $message Error message
     * @param array  $data    Data to pass to the view
     */
    protected function error(string $message, array $data = []): void
    {
        // Ensure CSRF token is re-generated
        CSRFToken::generate();

        $defaults = ['title' => $this->label('login.login')];
        $this->notify($message, 'error');
        $this->view('authentication.login', array_merge($defaults, $data));
    }
}
