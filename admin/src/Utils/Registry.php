<?php

namespace Formwork\Admin\Utils;

use Formwork\Utils\FileSystem;

class Registry
{
    /**
     * Stored registry entries
     *
     * @var array
     */
    protected $storage = [];

    /**
     * Registry filename
     *
     * @var string
     */
    protected $filename;

    /**
     * Whether the registry is saved
     *
     * @var bool
     */
    protected $saved = false;

    /**
     * Create a new Registry instance
     */
    public function __construct(string $filename)
    {
        $this->filename = $filename;
        if (FileSystem::exists($this->filename)) {
            $this->storage = (array) json_decode(FileSystem::read($filename), true);
            $this->saved = true;
        }
    }

    /**
     * Return whether a key is in the registry
     */
    public function has(string $key): bool
    {
        return isset($this->storage[$key]);
    }

    /**
     * Get a key from the registry
     */
    public function get(string $key)
    {
        if ($this->has($key)) {
            return $this->storage[$key];
        }
    }

    /**
     * Add a key to the registry
     */
    public function set(string $key, $value): void
    {
        $this->storage[$key] = $value;
        $this->saved = false;
    }

    /**
     * Remove a key from the registry
     */
    public function remove(string $key): void
    {
        if ($this->has($key)) {
            unset($this->storage[$key]);
            $this->saved = false;
        }
    }

    /**
     * Save the registry to file
     */
    public function save(): void
    {
        FileSystem::write($this->filename, json_encode($this->storage));
        $this->saved = true;
    }

    /**
     * Convert the registry to array
     */
    public function toArray(): array
    {
        return $this->storage;
    }

    /**
     * Save the registry on instance destruction
     */
    public function __destruct()
    {
        if (!$this->saved) {
            $this->save();
        }
    }
}
