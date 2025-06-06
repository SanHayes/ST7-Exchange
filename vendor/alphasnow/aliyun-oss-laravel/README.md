English | [简体中文](README-CN.md)  

# Aliyun OSS Laravel

[![Latest Stable Version](https://poser.pugx.org/alphasnow/aliyun-oss-laravel/v/stable)](https://packagist.org/packages/alphasnow/aliyun-oss-laravel)
[![Total Downloads](https://poser.pugx.org/alphasnow/aliyun-oss-laravel/downloads)](https://packagist.org/packages/alphasnow/aliyun-oss-laravel)
[![tests](https://github.com/alphasnow/aliyun-oss-laravel/actions/workflows/tests.yml/badge.svg?branch=4.x)](https://github.com/alphasnow/aliyun-oss-laravel/actions/workflows/tests.yml)
[![License](https://poser.pugx.org/alphasnow/aliyun-oss-laravel/license)](https://packagist.org/packages/alphasnow/aliyun-oss-laravel)
[![Coverage Status](https://coveralls.io/repos/github/alphasnow/aliyun-oss-laravel/badge.svg?branch=4)](https://coveralls.io/github/alphasnow/aliyun-oss-laravel?branch=4)

[![Banner](https://banners.beyondco.de/Aliyun%20OSS%20Laravel.png?theme=light&packageManager=composer+require&packageName=alphasnow%2Faliyun-oss-laravel&pattern=architect&style=style_1&description=Alibaba+Cloud+Object+Storage+Service+For+Laravel&md=1&showWatermark=1&fontSize=100px&images=https%3A%2F%2Flaravel.com%2Fimg%2Flogomark.min.svg)](https://github.com/alphasnow/aliyun-oss-laravel)

This package is a wrapper bridging [aliyun-oss-flysystem](https://github.com/alphasnow/aliyun-oss-flysystem) into Laravel as an available storage disk.  
If client direct transmission is required, Use web server signature direct transmission OSS extension package [aliyun-oss-appserver](https://github.com/alphasnow/aliyun-oss-appserver).  

## Compatibility

| laravel      | aliyun-oss-laravel | driver | readme |
|:-------------|:-------------------|:-------|:-------|
| \>=5.5,\<9.0 | ^3.0               | aliyun | [readme](https://github.com/alphasnow/aliyun-oss-laravel/blob/3.x/README.md) |
| \>=9.0       | ^4.0               | oss    | [readme](https://github.com/alphasnow/aliyun-oss-laravel/blob/4.x/README.md) |

## Installation
1. If you use the composer to manage project dependencies, run the following command in your project's root directory:
    ```bash
    composer require alphasnow/aliyun-oss-laravel
    ```
    Then run `composer install` to install the dependency.
2. Modify the environment file `.env`
    ```env
    OSS_ACCESS_KEY_ID=<Your aliyun accessKeyId, Required>
    OSS_ACCESS_KEY_SECRET=<Your aliyun accessKeySecret, Required>
    OSS_BUCKET=<Your oss bucket name, Required>
    OSS_ENDPOINT=<Your oss endpoint domain, Required>
    ```
3. (Optional) Modify the configuration file `config/filesystems.php`
    ```php
    "default" => env("FILESYSTEM_DRIVER", "oss"),
    // ...
    "disks"=>[
        // ...
        "oss" => [
            "driver"            => "oss",
            "access_key_id"     => env("OSS_ACCESS_KEY_ID"),           // Required, yourAccessKeyId
            "access_key_secret" => env("OSS_ACCESS_KEY_SECRET"),       // Required, yourAccessKeySecret
            "bucket"            => env("OSS_BUCKET"),                  // Required, for example: my-bucket
            "endpoint"          => env("OSS_ENDPOINT"),                // Required, for example: oss-cn-shanghai.aliyuncs.com
            "internal"          => env("OSS_INTERNAL", null),          // Optional, for example: oss-cn-shanghai-internal.aliyuncs.com
            "domain"            => env("OSS_DOMAIN", null),            // Optional, for example: oss.my-domain.com
            "is_cname"          => env("OSS_CNAME", false),            // Optional, if the Endpoint is a custom domain name, this must be true, see: https://github.com/aliyun/aliyun-oss-php-sdk/blob/572d0f8e099e8630ae7139ed3fdedb926c7a760f/src/OSS/OssClient.php#L113C1-L122C78
            "prefix"            => env("OSS_PREFIX", ""),              // Optional, the prefix of the store path
            "use_ssl"           => env("OSS_SSL", false),              // Optional, whether to use HTTPS
            "throw"             => env("OSS_THROW", false),            // Optional, whether to throw an exception that causes an error
            "signatureVersion"  => env("OSS_SIGNATURE_VERSION", "v1"), // Optional, select v1 or v4 as the signature version
            "region"            => env("OSS_REGION", ""),              // Optional, for example: cn-shanghai, used only when v4 signature version is selected
            "options"           => [],                                 // Optional, add global configuration parameters, For example: [\OSS\OssClient::OSS_CHECK_MD5 => false]
            "macros"            => []                                  // Optional, add custom Macro, For example: [\App\Macros\ListBuckets::class, \App\Macros\CreateBucket::class]
        ],
        // ...
    ]
    ```

## Usage
```php
use Illuminate\Support\Facades\Storage;
$storage = Storage::disk("oss");
```

### Use storage method
#### Write
```php
Storage::disk("oss")->putFile("dir/path", "/local/path/file.txt");
Storage::disk("oss")->putFileAs("dir/path", "/local/path/file.txt", "file.txt");

Storage::disk("oss")->put("dir/path/file.txt", file_get_contents("/local/path/file.txt"));
$fp = fopen("/local/path/file.txt","r");
Storage::disk("oss")->put("dir/path/file.txt", $fp);
fclose($fp);

Storage::disk("oss")->prepend("dir/path/file.txt", "Prepend Text"); 
Storage::disk("oss")->append("dir/path/file.txt", "Append Text");

Storage::disk("oss")->put("dir/path/secret.txt", "My secret", "private");
Storage::disk("oss")->put("dir/path/download.txt", "Download content", ["headers" => ["Content-Disposition" => "attachment;filename=download.txt"]]);
```

#### Read
```php
Storage::disk("oss")->url("dir/path/file.txt");
Storage::disk("oss")->temporaryUrl("dir/path/file.txt", \Carbon\Carbon::now()->addMinutes(30));

Storage::disk("oss")->get("dir/path/file.txt"); 

Storage::disk("oss")->exists("dir/path/file.txt"); 
Storage::disk("oss")->size("dir/path/file.txt"); 
Storage::disk("oss")->lastModified("dir/path/file.txt");
```

#### Delete
```php
Storage::disk("oss")->delete("dir/path/file.txt");
Storage::disk("oss")->delete(["dir/path/file1.txt", "dir/path/file2.txt"]);
```

#### File operation
```php
Storage::disk("oss")->copy("dir/path/file.txt", "dir/path/file_new.txt");
Storage::disk("oss")->move("dir/path/file.txt", "dir/path/file_new.txt");
Storage::disk("oss")->rename("dir/path/file.txt", "dir/path/file_new.txt");
```

#### Folder operation
```php
Storage::disk("oss")->makeDirectory("dir/path"); 
Storage::disk("oss")->deleteDirectory("dir/path");

Storage::disk("oss")->files("dir/path");
Storage::disk("oss")->allFiles("dir/path");

Storage::disk("oss")->directories("dir/path"); 
Storage::disk("oss")->allDirectories("dir/path"); 
```

### Use macro
#### default macro
```php
Storage::disk("oss")->appendObject("dir/path/news.txt", "The first line paragraph.", 0);
Storage::disk("oss")->appendObject("dir/path/news.txt", "The second line paragraph.", 25);
Storage::disk("oss")->appendObject("dir/path/news.txt", "The last line paragraph.", 51);

Storage::disk("oss")->appendFile("dir/path/file.zip", "dir/path/file.zip.001", 0);
Storage::disk("oss")->appendFile("dir/path/file.zip", "dir/path/file.zip.002", 1024);
Storage::disk("oss")->appendFile("dir/path/file.zip", "dir/path/file.zip.003", 1024);

Storage::disk("oss")->processObject("dir/path/image.jpg", "image/resize,l_1000");
```

#### Add custom macro
1. Add Macro
    ```php
    namespace App\Macros;
    use AlphaSnow\LaravelFilesystem\Aliyun\Macros\AliyunMacro;
    
    class ListBuckets implements AliyunMacro
    {
        // ... 
    }
    ```
   Reference code: [AppendObject.php](https://github.com/alphasnow/aliyun-oss-laravel/blob/4.5.0/src/Macros/AppendObject.php)
2. Modify the config
    ```php
    [
        "macros" => [\App\Macros\ListBuckets::class]
    ]
    ```
3. Use Macro
    ```php
    Storage::disk("oss")->listBuckets()
    ```

### Use OssClient
```php
use AlphaSnow\LaravelFilesystem\Aliyun\OssClientAdapter;

$adapter = new OssClientAdapter(Storage::disk("oss"));
$adapter->client()->appendObject($adapter->bucket(), $adapter->path("dir/path/file.txt"), "contents", 0, $adapter->options(["visibility" => "private"]));
```

## Documentation
[AlibabaCloud Object Storage Service Documentation](https://www.alibabacloud.com/help/en/oss/)

## Issues
[Opening an Issue](https://github.com/alphasnow/aliyun-oss-laravel/issues)

## Contributors
<a href="https://github.com/alphasnow/aliyun-oss-laravel/graphs/contributors">
  <img src="https://contrib.rocks/image?repo=alphasnow/aliyun-oss-laravel" />
</a>

## Star History
[![Star History Chart](https://api.star-history.com/svg?repos=alphasnow/aliyun-oss-laravel&type=Timeline)](https://star-history.com/#alphasnow/aliyun-oss-laravel&Timeline)

## License
[MIT](LICENSE)