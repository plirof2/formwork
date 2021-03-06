<?php

namespace Formwork\Files;

use Formwork\Core\Formwork;
use Formwork\Utils\FileSystem;
use Formwork\Utils\HTTPRequest;
use Formwork\Utils\Str;
use Formwork\Utils\Uri;

class File
{
    /**
     * File path
     *
     * @var string
     */
    protected $path;

    /**
     * File name
     *
     * @var string
     */
    protected $name;

    /**
     * File extension
     *
     * @var string
     */
    protected $extension;

    /**
     * File uri
     *
     * @var string
     */
    protected $uri;

    /**
     * File MIME type
     *
     * @var string
     */
    protected $mimeType;

    /**
     * File type (image, text, audio, video or document)
     *
     * @var string
     */
    protected $type;

    /**
     * File size in a human-readable format
     *
     * @var string
     */
    protected $size;

    /**
     * Create a new File instance
     */
    public function __construct(string $path)
    {
        $this->path = $path;
        $this->name = basename($path);
        $this->extension = FileSystem::extension($path);
        $this->mimeType = FileSystem::mimeType($path);
        $this->type = $this->type();
        $this->uri = Uri::resolveRelativeUri($this->name, HTTPRequest::root() . ltrim(Formwork::instance()->request(), '/'));
        $this->size = FileSystem::size($path);
    }

    /**
     * Get file path
     */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * Get file name
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Get file extension
     */
    public function extension(): string
    {
        return $this->extension;
    }

    /**
     * Get file URI
     */
    public function uri(): string
    {
        return $this->uri;
    }

    /**
     * Get file MIME type
     */
    public function mimeType(): string
    {
        return $this->mimeType;
    }

    /**
     * Get file type
     */
    public function type(): ?string
    {
        if ($this->type !== null) {
            return $this->type;
        }
        if (Str::startsWith($this->mimeType, 'image')) {
            return 'image';
        }
        if (Str::startsWith($this->mimeType, 'text')) {
            return 'text';
        }
        if (Str::startsWith($this->mimeType, 'audio')) {
            return 'audio';
        }
        if (Str::startsWith($this->mimeType, 'video')) {
            return 'video';
        }
        if (in_array($this->extension, ['pdf', 'doc', 'docx', 'odt'], true)) {
            return 'document';
        }
        if (in_array($this->extension, ['gz', '7z', 'bz2', 'rar', 'tar', 'zip'], true)) {
            return 'archive';
        }
        return null;
    }

    /**
     * Get file size
     */
    public function size(): string
    {
        return $this->size;
    }

    public function __toString(): string
    {
        return $this->name;
    }
}
