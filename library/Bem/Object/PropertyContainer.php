<?php

namespace Icinga\Module\Bem\Object;

use Icinga\Exception\IcingaException as IE;

trait PropertyContainer
{
    private $properties = [];

    private $modifiedProperties = [];

    private $hasBeenModified = false;

    protected function fillWithDefaultProperties()
    {
        $this->properties = $this->getDefaultProperties();
        $this->setUnmodified();
    }

    protected function setUnmodified()
    {
        $this->hasBeenModified = false;
        $this->modifiedProperties = [];
    }

    /**
     * Getter
     *
     * @param string $property Property
     *
     * @throws IE
     *
     * @return mixed
     */
    public function get($property)
    {
        $this->assertPropertyExists($property);

        return $this->properties[$property];
    }

    protected function assertPropertyExists($key)
    {
        if (! array_key_exists($key, $this->properties)) {
            throw new IE('Trying to get invalid property "%s"', $key);
        }

        return $this;
    }

    public function hasProperty($key)
    {
        if (array_key_exists($key, $this->properties)) {
            return true;
        } elseif ($key === 'id') {
            // There is getId, would give false positive
            return false;
        }
        $func = 'get' . ucfirst($key);
        if (substr($func, -2) === '[]') {
            $func = substr($func, 0, -2);
        }
        if (method_exists($this, $func)) {
            return true;
        }
        return false;
    }

    /**
     * Generic setter
     *
     * @param string $key
     * @param mixed  $value
     *
     * @throws IE
     *
     * @return self
     */
    public function set($key, $value)
    {
        $this->assertPropertyExists($key);

        return $this->reallySet($key, $value);
    }

    protected function reallySet($key, $value)
    {
        if ($value === $this->properties[$key]) {
            return $this;
        }

        $this->hasBeenModified = true;
        $this->modifiedProperties[$key] = true;
        $this->properties[$key] = $value;

        return $this;
    }

    /**
     * Magic getter
     *
     * @param mixed $key
     *
     * @return mixed
     */
    public function __get($key)
    {
        return $this->get($key);
    }

    /**
     * Magic setter
     *
     * @param  string  $key  Key
     * @param  mixed   $val  Value
     *
     * @return void
     */
    public function __set($key, $val)
    {
        $this->set($key, $val);
    }

    /**
     * Magic isset check
     *
     * @param  string $key
     * @return boolean
     */
    public function __isset($key)
    {
        return array_key_exists($key, $this->properties);
    }

    /**
     * Magic unsetter
     *
     * @param string $key
     * @throws IE
     * @return void
     */
    public function __unset($key)
    {
        $this->assertPropertyExists($key);

        $default = $this->getDefaultProperties();
        $this->properties[$key] = $default[$key];
    }

    /**
     * Runs set() for every key/value pair of the given Array
     *
     * @param  array|\stdClass[] $properties
     * @return $this
     */
    public function setProperties($properties)
    {
        foreach ($properties as $key => $value) {
            $this->set($key, $value);
        }

        return $this;
    }

    /**
     * Return an array with all object properties
     *
     * @return array
     */
    public function getProperties()
    {
        $res = [];

        foreach ($this->listProperties() as $key) {
            $res[$key] = $this->get($key);
        }

        return $res;
    }

    protected function getPropertiesForDb()
    {
        return $this->properties;
    }

    public function listProperties()
    {
        return array_keys($this->properties);
    }

    /**
     * Return all properties that changed since object creation
     *
     * @return array
     */
    public function getModifiedProperties()
    {
        $properties = array();
        foreach (array_keys($this->modifiedProperties) as $key) {
            $properties[$key] = $this->get($key);
        }

        return $properties;
    }

    /**
     * List all properties that changed since object creation
     *
     * @return array
     */
    public function listModifiedProperties()
    {
        return array_keys($this->modifiedProperties);
    }

    /**
     * Whether this object has been modified
     *
     * @return bool
     */
    public function hasBeenModified()
    {
        return $this->hasBeenModified;
    }

    /**
     * Whether the given property has been modified
     *
     * @param  string   $key Property name
     * @return boolean
     */
    protected function hasModifiedProperty($key)
    {
        return array_key_exists($key, $this->modifiedProperties);
    }

    protected function getDefaultProperties()
    {
        if (property_exists($this, 'defaultProperties')) {
            return $this->defaultProperties;
        }

        return [];
    }
}
