<?php

namespace Formwork\Admin;

use Formwork\Admin\Security\CSRFToken;
use Formwork\Admin\Users\User;
use Formwork\Admin\Users\Users;
use Formwork\Admin\Utils\JSONResponse;
use Formwork\Admin\Utils\Session;
use Formwork\Core\Formwork;
use Formwork\Router\RouteParams;
use Formwork\Router\Router;
use Formwork\Traits\SingletonTrait;
use Formwork\Utils\FileSystem;
use Formwork\Utils\HTTPRequest;
use Formwork\Utils\Uri;
use BadMethodCallException;
use RuntimeException;
use Throwable;

class Admin
{
    use AdminTrait;
    use SingletonTrait;

    /**
     * Admin accounts path
     *
     * @var string
     */
    public const ACCOUNTS_PATH = ADMIN_PATH . 'accounts' . DS;

    /**
     * Admin schemes path
     *
     * @var string
     */
    public const SCHEMES_PATH = ADMIN_PATH . 'schemes' . DS;

    /**
     * Admin translations path
     *
     * @var string
     */
    public const TRANSLATIONS_PATH = ADMIN_PATH . 'translations' . DS;

    /**
     * Admin logs path
     *
     * @var string
     */
    public const LOGS_PATH = ADMIN_PATH . 'logs' . DS;

    /**
     * Admin views path
     *
     * @var string
     */
    public const VIEWS_PATH = ADMIN_PATH . 'views' . DS;

    /**
     * Router instance
     *
     * @var Router
     */
    protected $router;

    /**
     * All the registered users
     *
     * @var Users
     */
    protected $users;

    /**
     * Translation instance
     *
     * @var Translation
     */
    protected $translation;

    /**
     * Errors controller
     *
     * @var Controllers\Errors
     */
    protected $errors;

    /**
     * Create a new Admin instance
     */
    public function __construct()
    {
        $this->initializeSingleton();

        $this->router = new Router(Uri::removeQuery($this->route()));
        $this->users = Users::load();

        $this->loadTranslations();
        $this->loadErrorHandler();
    }

    /**
     * Return whether a user is logged in
     */
    public function isLoggedIn(): bool
    {
        $username = Session::get('FORMWORK_USERNAME');
        return !empty($username) && $this->users->has($username);
    }

    /**
     * Return all registered users
     */
    public function users(): Users
    {
        return $this->users;
    }

    /**
     * Return currently logged in user
     */
    public function user(): User
    {
        $username = Session::get('FORMWORK_USERNAME');
        return $this->users->get($username);
    }

    /**
     * Get current translation
     */
    public function translation(): Translation
    {
        return $this->translation;
    }

    /**
     * Run the administration panel
     */
    public function run(): void
    {
        if (HTTPRequest::method() === 'POST') {
            $this->validateContentLength();
            $this->validateCSRFToken();
        }

        if ($this->users->isEmpty()) {
            $this->registerAdmin();
        }

        if (!$this->isLoggedIn() && $this->route() !== '/login/') {
            Session::set('FORMWORK_REDIRECT_TO', $this->route());
            $this->redirect('/login/');
        }

        $this->loadRoutes();

        $this->router->dispatch();

        if (!$this->router->hasDispatched()) {
            $this->errors->notFound();
        }
    }

    /**
     * Load proper panel translation
     */
    protected function loadTranslations(): void
    {
        $languageCode = Formwork::instance()->option('admin.lang');
        if ($this->isLoggedIn()) {
            $languageCode = $this->user()->language();
        }
        $this->translation = Translation::load($languageCode);
    }

    /**
     * Load the panel-styled error handler
     */
    protected function loadErrorHandler(): void
    {
        $this->errors = new Controllers\Errors();
        set_exception_handler(function (Throwable $exception): void {
            $this->errors->internalServerError($exception);
            throw $exception;
        });
    }

    /**
     * Validate HTTP request Content-Length according to post_max_size directive
     * and notify if not valid
     */
    protected function validateContentLength(): void
    {
        if (HTTPRequest::contentLength() !== null) {
            $maxSize = FileSystem::shorthandToBytes(ini_get('post_max_size'));
            if (HTTPRequest::contentLength() > $maxSize && $maxSize > 0) {
                $this->notify($this->label('request.error.post-max-size'), 'error');
                $this->redirectToReferer();
            }
        }
    }

    /**
     * Validate CSRF token and redirect to login view if not valid
     */
    protected function validateCSRFToken(): void
    {
        try {
            CSRFToken::validate();
        } catch (RuntimeException $e) {
            CSRFToken::destroy();
            Session::remove('FORMWORK_USERNAME');
            $this->notify($this->label('login.suspicious-request-detected'), 'warning');
            if (HTTPRequest::isXHR()) {
                JSONResponse::error('Bad Request: the CSRF token is not valid', 400)->send();
            }
            $this->redirect('/login/');
        }
    }

    /**
     * Register administration panel if no user exists
     */
    protected function registerAdmin(): void
    {
        if (!HTTPRequest::isLocalhost()) {
            $this->redirectToSite();
        }
        if ($this->router->request() !== '/') {
            $this->redirectToPanel();
        }
        $controller = new Controllers\Register();
        $controller->register();
        exit;
    }

    /**
     * Load administration panel routes
     */
    protected function loadRoutes(): void
    {
        // Default route
        $this->router->add(
            '/',
            function (RouteParams $params): void {
                $this->redirect('/dashboard/');
            }
        );

        // Authentication
        $this->router->add(
            ['GET', 'POST'],
            '/login/',
            Controllers\Authentication::class . '@login'
        );
        $this->router->add(
            '/logout/',
            Controllers\Authentication::class . '@logout'
        );

        // Backup
        $this->router->add(
            'XHR',
            'POST',
            '/backup/make/',
            Controllers\Backup::class . '@make'
        );
        $this->router->add(
            'POST',
            '/backup/download/{backup}/',
            Controllers\Backup::class . '@download'
        );

        // Cache
        $this->router->add(
            'XHR',
            'POST',
            '/cache/clear/',
            Controllers\Cache::class . '@clear'
        );

        // Dashboard
        $this->router->add(
            '/dashboard/',
            Controllers\Dashboard::class . '@index'
        );

        // Options
        $this->router->add(
            '/options/',
            Controllers\Options::class . '@index'
        );
        $this->router->add(
            ['GET', 'POST'],
            '/options/system/',
            Controllers\Options::class . '@systemOptions'
        );
        $this->router->add(
            ['GET', 'POST'],
            '/options/site/',
            Controllers\Options::class . '@siteOptions'
        );
        $this->router->add(
            '/options/updates/',
            Controllers\Options::class . '@updates'
        );
        $this->router->add(
            '/options/info/',
            Controllers\Options::class . '@info'
        );

        // Pages
        $this->router->add(
            '/pages/',
            Controllers\Pages::class . '@index'
        );
        $this->router->add(
            'POST',
            '/pages/new/',
            Controllers\Pages::class . '@create'
        );
        $this->router->add(
            ['GET', 'POST'],
            [
                '/pages/{page}/edit/',
                '/pages/{page}/edit/language/{language}/'
            ],
            Controllers\Pages::class . '@edit'
        );
        $this->router->add(
            'XHR',
            'POST',
            '/pages/reorder/',
            Controllers\Pages::class . '@reorder'
        );
        $this->router->add(
            'POST',
            '/pages/{page}/file/upload/',
            Controllers\Pages::class . '@uploadFile'
        );
        $this->router->add(
            'POST',
            '/pages/{page}/file/{filename}/delete/',
            Controllers\Pages::class . '@deleteFile'
        );
        $this->router->add(
            'POST',
            [
                '/pages/{page}/delete/',
                '/pages/{page}/delete/language/{language}/',
            ],
            Controllers\Pages::class . '@delete'
        );

        // Updates
        $this->router->add(
            'XHR',
            'POST',
            '/updates/check/',
            Controllers\Updates::class . '@check'
        );
        $this->router->add(
            'XHR',
            'POST',
            '/updates/update/',
            Controllers\Updates::class . '@update'
        );

        // Users
        $this->router->add(
            '/users/',
            Controllers\Users::class . '@index'
        );
        $this->router->add(
            'POST',
            '/users/new/',
            Controllers\Users::class . '@create'
        );
        $this->router->add(
            'POST',
            '/users/{user}/delete/',
            Controllers\Users::class . '@delete'
        );
        $this->router->add(
            ['GET', 'POST'],
            '/users/{user}/profile/',
            Controllers\Users::class . '@profile'
        );
    }

    public function __call(string $name, array $arguments)
    {
        if (method_exists(AdminTrait::class, $name)) {
            return $this->$name(...$arguments);
        }
        throw new BadMethodCallException('Call to undefined method ' . static::class . '::' . $name . '()');
    }
}
