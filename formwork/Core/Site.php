<?php

namespace Formwork\Core;

use Formwork\Languages\Languages;
use Formwork\Metadata\Metadata;
use Formwork\Utils\FileSystem;
use RuntimeException;

class Site extends AbstractPage
{
    /**
     * Array containing all loaded pages
     *
     * @var array
     */
    protected $storage = [];

    /**
     * Current page
     *
     * @var Page
     */
    protected $currentPage;

    /**
     * Array containing all available templates
     *
     * @var array
     */
    protected $templates = [];

    /**
     * Site languages
     *
     * @var Languages
     */
    protected $languages;

    /**
     * Create a new Site instance
     */
    public function __construct(array $data)
    {
        $this->path = Formwork::instance()->option('content.path');
        $this->relativePath = DS;
        $this->route = '/';
        $this->data = array_merge($this->defaults(), $data);
        $this->loadTemplates();
    }

    /**
     * Return site default data
     */
    public function defaults(): array
    {
        return [
            'title'    => 'Formwork',
            'aliases'  => [],
            'metadata' => []
        ];
    }

    /**
     * Get all available templates
     */
    public function templates(): array
    {
        return array_map('strval', array_keys($this->templates));
    }

    /**
     * Return whether a template exists
     */
    public function hasTemplate(string $template): bool
    {
        return array_key_exists($template, $this->templates);
    }

    /**
     * Return template filename
     */
    public function template(string $name): string
    {
        if (!$this->hasTemplate($name)) {
            throw new RuntimeException('Invalid template ' . $name);
        }
        return $this->templates[$name];
    }

    /**
     * Return whether site has been modified since given time
     */
    public function modifiedSince(int $time): bool
    {
        return FileSystem::directoryModifiedSince($this->path, $time);
    }

    /**
     * @inheritdoc
     */
    public function parent()
    {
        return null;
    }

    /**
     * Return a PageCollection containing site pages
     */
    public function pages(): PageCollection
    {
        return $this->children();
    }

    /**
     * Return whether site has pages
     */
    public function hasPages(): bool
    {
        return !$this->children()->isEmpty();
    }

    /**
     * Return alias of a given route
     *
     * @return string|void
     */
    public function alias(string $route)
    {
        if ($this->has('aliases')) {
            $route = trim($route, '/');
            if (isset($this->data['aliases'][$route])) {
                return $this->data['aliases'][$route];
            }
        }
    }

    /**
     * Set and return site current page
     */
    public function setCurrentPage(Page $page): Page
    {
        return $this->currentPage = $page;
    }

    /**
     * Navigate to and return a page from its route, setting then the current page
     */
    public function navigate(string $route): Page
    {
        return $this->currentPage = $this->findPage($route);
    }

    /**
     * Set site languages
     */
    public function setLanguages(Languages $languages): void
    {
        $this->languages = $languages;
    }

    /**
     * Get site index page
     */
    public function indexPage(): ?Page
    {
        return $this->findPage(Formwork::instance()->option('pages.index'));
    }

    /**
     * Return or render site error page
     *
     * @param bool $navigate Whether to navigate to the error page or not
     */
    public function errorPage(bool $navigate = false): ?Page
    {
        $errorPage = $this->findPage(Formwork::instance()->option('pages.error'));
        if ($navigate) {
            $this->currentPage = $errorPage;
        }
        return $errorPage;
    }

    /**
     * Get site language
     *
     * @deprecated
     */
    public function lang(): ?string
    {
        trigger_error(static::class . '::lang() is deprecated since Formwork 1.2.0, use ' . static::class . '::languages()->default() instead', E_USER_DEPRECATED);
        return $this->languages()->default() ?? ($this->data['lang'] ?? 'en');
    }

    /**
     * @inheritdoc
     */
    public function isSite(): bool
    {
        return true;
    }

    /**
     * @inheritdoc
     */
    public function isIndexPage(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function isErrorPage(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function isDeletable(): bool
    {
        return false;
    }

    /**
     * @inheritdoc
     */
    public function metadata(): Metadata
    {
        if ($this->metadata !== null) {
            return $this->metadata;
        }
        $defaults = [
            'charset'     => Formwork::instance()->option('charset'),
            'author'      => $this->get('author'),
            'description' => $this->get('description'),
            'generator'   => 'Formwork'
        ];
        $data = array_filter(array_merge($defaults, $this->data['metadata']));
        if (!Formwork::instance()->option('metadata.set_generator')) {
            unset($data['generator']);
        }
        return $this->metadata = new Metadata($data);
    }

    /**
     * Find page from route
     */
    public function findPage(string $route): ?Page
    {
        if ($route === '/') {
            return $this->indexPage();
        }

        $components = explode('/', trim($route, '/'));
        $path = $this->path;

        foreach ($components as $component) {
            $found = false;
            foreach (FileSystem::listDirectories($path) as $dir) {
                if (preg_replace(Page::NUM_REGEX, '', $dir) === $component) {
                    $path .= $dir . DS;
                    $found = true;
                    break;
                }
            }
            if (!$found) {
                return null;
            }
        }

        $page = $this->retrievePage($path);

        return !$page->isEmpty() ? $page : null;
    }

    /**
     * Retrieve page from the storage creating a new one if not existing
     */
    public function retrievePage(string $path): Page
    {
        if (isset($this->storage[$path])) {
            return $this->storage[$path];
        }
        return $this->storage[$path] = new Page($path);
    }

    /**
     * Load all available templates
     */
    protected function loadTemplates(): void
    {
        $templatesPath = Formwork::instance()->option('templates.path');
        $templates = [];
        foreach (FileSystem::listFiles($templatesPath) as $file) {
            $templates[FileSystem::name($file)] = $templatesPath . $file;
        }
        $this->templates = $templates;
    }

    public function __toString(): string
    {
        return 'site';
    }
}
