<?php

namespace Formwork\Core;

use Formwork\Metadata\Metadata;
use Formwork\Utils\Arr;
use Formwork\Utils\FileSystem;
use Formwork\Utils\HTTPRequest;
use Formwork\Utils\Uri;
use BadMethodCallException;

abstract class AbstractPage
{
    /**
     * Page path
     *
     * @var string
     */
    protected $path;

    /**
     * Page path relative to content path
     *
     * @var string
     */
    protected $relativePath;

    /**
     * Page route
     *
     * @var string
     */
    protected $route;

    /**
     * Page data
     *
     * @var array
     */
    protected $data = [];

    /**
     * Page uri
     *
     * @var string
     */
    protected $uri;

    /**
     * Page absolute uri
     *
     * @var string
     */
    protected $absoluteUri;

    /**
     * Page last modified time
     *
     * @var int
     */
    protected $lastModifiedTime;

    /**
     * Page modified date
     *
     * @var string
     */
    protected $date;

    /**
     * Page metadata
     *
     * @var Metadata
     */
    protected $metadata;

    /**
     * Page parent
     *
     * @var Page|Site
     */
    protected $parent;

    /**
     * PageCollection containing page parents
     *
     * @var PageCollection
     */
    protected $parents;

    /**
     * Page level
     *
     * @var int
     */
    protected $level;

    /**
     * PageCollection containing page children
     *
     * @var PageCollection
     */
    protected $children;

    /**
     * PageCollection containing page descendants
     *
     * @var PageCollection
     */
    protected $descendants;

    /**
     * Return a URI relative to page
     *
     * @param bool|string $includeLanguage
     */
    public function uri(string $path = '', $includeLanguage = true): string
    {
        $base = HTTPRequest::root();
        if ($includeLanguage) {
            $language = is_string($includeLanguage) ? $includeLanguage : Formwork::instance()->site()->languages()->current();

            $default = Formwork::instance()->site()->languages()->default();
            $preferred = Formwork::instance()->site()->languages()->preferred();

            if (($language !== null && $language !== $default) || ($preferred !== null && $preferred !== $default)) {
                $base .= $language . '/';
            }
        }
        return $base . ltrim($this->route, '/') . ltrim($path, '/');
    }

    /**
     * Get page absolute URI
     */
    public function absoluteUri(): string
    {
        if ($this->absoluteUri !== null) {
            return $this->absoluteUri;
        }
        return $this->absoluteUri = Uri::resolveRelativeUri($this->uri());
    }

    /**
     * Get page last modified time
     */
    public function lastModifiedTime(): int
    {
        if ($this->lastModifiedTime !== null) {
            return $this->lastModifiedTime;
        }
        return $this->lastModifiedTime = FileSystem::lastModifiedTime($this->path);
    }

    /**
     * Return page date optionally in a given format
     *
     * @param string $format
     */
    public function date(string $format = null): string
    {
        if ($format === null) {
            $format = Formwork::instance()->option('date.format');
        }
        return date($format, $this->lastModifiedTime());
    }

    /**
     * Get parent page
     *
     * @return Page|Site|null
     */
    public function parent()
    {
        if ($this->parent !== null) {
            return $this->parent;
        }
        $parentPath = dirname($this->path) . DS;
        if (FileSystem::isDirectory($parentPath) && $parentPath !== Formwork::instance()->option('content.path')) {
            return $this->parent = Formwork::instance()->site()->retrievePage($parentPath);
        }
        // If no parent was found returns the site as first level pages' parent
        return $this->parent = Formwork::instance()->site();
    }

    /**
     * Return a PageCollection containing page parents
     */
    public function parents(): PageCollection
    {
        if ($this->parents !== null) {
            return $this->parents;
        }
        $parentPages = [];
        $page = $this;
        while (($parent = $page->parent()) !== null) {
            $parentPages[] = $parent;
            $page = $parent;
        }
        return $this->parents = new PageCollection(array_reverse($parentPages));
    }

    /**
     * Return whether page has parents
     */
    public function hasParents(): bool
    {
        return !$this->parents()->isEmpty();
    }

    /**
     * Return a PageCollection containing page children
     */
    public function children(): PageCollection
    {
        if ($this->children !== null) {
            return $this->children;
        }
        return $this->children = PageCollection::fromPath($this->path);
    }

    /**
     * Return whether page has children
     */
    public function hasChildren(): bool
    {
        return !$this->children()->isEmpty();
    }

    /**
     * Return a PageCollection containing page descendants
     */
    public function descendants(): PageCollection
    {
        if ($this->descendants !== null) {
            return $this->descendants;
        }
        return $this->descendants = PageCollection::fromPath($this->path, true);
    }

    /**
     * Return whether page has descendants
     */
    public function hasDescendants(): bool
    {
        foreach ($this->children() as $child) {
            if ($child->hasChildren()) {
                return true;
            }
        }
        return false;
    }

    /**
     * Return page level
     */
    public function level(): int
    {
        if ($this->level !== null) {
            return $this->level;
        }
        return $this->level = $this->parents()->count();
    }

    /**
     * Return whether current page is Site
     */
    abstract public function isSite(): bool;

    /**
     * Return whether current page is index page
     */
    abstract public function isIndexPage(): bool;

    /**
     * Return whether current page is error page
     */
    abstract public function isErrorPage(): bool;

    /**
     * Return whether current page is deletable
     */
    abstract public function isDeletable(): bool;

    /**
     * Return page metadata
     */
    abstract public function metadata(): Metadata;

    /**
     * Get page data by key
     *
     * @param string $key
     * @param mixed  $default Default value if key is not set
     */
    public function get($key, $default = null)
    {
        if (!is_string($key)) {
            trigger_error('Using ' . static::class . '::get() with a non-string $key parameter is deprecated since Formwork 1.10.0', E_USER_DEPRECATED);
        }
        if (property_exists($this, $key)) {
            // Call getter method if exists and property is null
            if ($this->$key === null && method_exists($this, $key)) {
                return $this->$key();
            }
            return $this->$key;
        }
        if (Arr::has($this->data, $key)) {
            return Arr::get($this->data, $key, $default);
        }
        if (method_exists($this, $key)) {
            trigger_error('Accessing non-getter methods from ' . static::class . '::get() is deprecated since Formwork 1.3.0, use $page->' . $key . '() instead', E_USER_DEPRECATED);
            return $this->$key();
        }
        return $default;
    }

    /**
     * Return whether page data has a key
     *
     * @param string $key
     */
    public function has($key): bool
    {
        if (!is_string($key)) {
            trigger_error('Using ' . static::class . '::has() with a non-string $key parameter is deprecated since Formwork 1.10.0', E_USER_DEPRECATED);
            $key = (string) $key;
        }
        return property_exists($this, $key) || Arr::has($this->data, $key);
    }

    /**
     * Set page data
     *
     * @param string $key
     */
    public function set($key, $value): void
    {
        if (!is_string($key)) {
            trigger_error('Using ' . static::class . '::set() with a non-string $key parameter is deprecated since Formwork 1.10.0', E_USER_DEPRECATED);
        }
        $this->data[$key] = $value;
    }

    public function __call(string $name, array $arguments)
    {
        if ($this->has($name)) {
            return $this->get($name);
        }
        throw new BadMethodCallException('Call to undefined method ' . static::class . '::' . $name . '()');
    }
}
