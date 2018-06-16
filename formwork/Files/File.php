<?php

namespace Formwork\Files;
use Formwork\Utils\FileSystem;
use Formwork\Utils\MimeType;
use Exception;

class File {

    protected $path;

    protected $name;

    protected $extension;

    protected $mimeType;

    protected $type;

    public function __construct($path) {
        $this->path = $path;
        $this->name = FileSystem::basename($path);
        $this->extension = FileSystem::extension($path);
        $this->mimeType = FileSystem::mimeType($path);
        $this->type = $this->type();
    }

    public function type() {
        if (!is_null($this->type)) return $this->type;
        if (strpos($this->mimeType, 'image') === 0) {
            return 'image';
        } elseif (strpos($this->mimeType, 'text') === 0) {
            return 'text';
        } elseif (strpos($this->mimeType, 'audio') === 0) {
            return 'audio';
        } elseif (strpos($this->mimeType, 'video') === 0) {
            return 'video';
        } elseif (in_array($this->extension, array('pdf', 'doc', 'docx', 'odt'))) {
            return 'document';
        }
        return null;
    }

    public function __call($name, $arguments) {
        if (property_exists($this, $name)) return $this->$name;
        throw new Exception('Invalid method');
    }

    public function __toString() {
        return $this->name;
    }

}
