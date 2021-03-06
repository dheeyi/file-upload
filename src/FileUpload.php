<?php

namespace CrCms\Upload;

use Illuminate\Contracts\Config\Repository as Config;
use CrCms\Upload\Contracts\FileUpload as FileUploadContract;

/**
 * Class FileUpload
 *
 * @package CrCms\Upload
 */
class FileUpload
{
    /**
     * @var Config
     */
    protected $config;

    /**
     * @var FileUploadContract
     */
    protected $driver;

    /**
     * FileUpload constructor.
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        set_time_limit(5 * 60);

        $this->config = $config;
        $this->config($this->config->get('upload.default'));
    }

    /**
     * @param string $uploadType
     * @return FileUpload
     */
    public function config(string $uploadType): self
    {
        {
            $driver = $this->config->get("upload.uploads.{$uploadType}.driver");
            $this->setDriver(new $driver($this->config));
        }

        {
            $allowFunc = [
                'setFileSize',
                'setRename',
                'setCheckMime',
                'setCheckExtension',
                'setExtensions',
                'setHashDirLayer',
                'setPath',
            ];
            foreach ($this->config->get("upload.uploads.{$uploadType}.options") as $key => $value) {
                if (in_array($key, $allowFunc, true)) {
                    call_user_func([$this, $key], $value);
                }
            }
        }

        return $this;
    }

    /**
     * @return array
     */
    public function upload()
    {
        return array_map([$this,'uploadFile'], $this->formatFiles());
    }

    /**
     * @param FileUploadContract $driver
     * @return FileUpload
     */
    public function setDriver(FileUploadContract $driver): self
    {
        $this->driver = $driver;

        return $this;
    }

    /**
     * @return FileUploadContract
     */
    public function getDriver(): FileUploadContract
    {
        return $this->driver;
    }

    /**
     * @return array
     */
    protected function formatFiles(): array
    {
        $files = [];
        if (!empty($_FILES)) {
            $temp = [];
            foreach ($_FILES as $key => $values) {
                if (is_array($values['name'])) {
                    foreach ($values['name'] as $k => $vo) {
                        if (empty($vo)) continue;
                        $temp['name'] = $vo;
                        $temp['type'] = $values['type'][$k];
                        $temp['tmp_name'] = $values['tmp_name'][$k];
                        $temp['error'] = $values['error'][$k];
                        $temp['size'] = $values['size'][$k];
                        $temp['__name'] = $key;
                        $files[] = $temp;
                    }
                } else {
                    if (empty($values['name'])) continue;
                    $values['__name'] = $key;
                    $files[] = $values;
                }
            }
        }

        return $files;
    }

    /**
     * @param array $file
     * @return mixed
     */
    protected function uploadFile(array $file)
    {
        return $this->driver->upload($file);
    }

    /**
     * @param string $name
     * @param array $arguments
     * @return $this|mixed
     */
    public function __call(string $name, array $arguments)
    {
        if (method_exists($this->driver, $name)) {
            $result = call_user_func_array([$this->driver, $name], $arguments);
            if ($result instanceof $this->driver) {
                return $this;
            }
            return $result;
        }

        throw new \BadMethodCallException("method [{$name}] is not exists");
    }
}