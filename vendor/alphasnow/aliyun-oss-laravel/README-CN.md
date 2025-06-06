[English](README.md) | 简体中文  

# Aliyun OSS Laravel

[![Latest Stable Version](https://poser.pugx.org/alphasnow/aliyun-oss-laravel/v/stable)](https://packagist.org/packages/alphasnow/aliyun-oss-laravel)
[![Total Downloads](https://poser.pugx.org/alphasnow/aliyun-oss-laravel/downloads)](https://packagist.org/packages/alphasnow/aliyun-oss-laravel)
[![tests](https://github.com/alphasnow/aliyun-oss-laravel/actions/workflows/tests.yml/badge.svg?branch=4.x)](https://github.com/alphasnow/aliyun-oss-laravel/actions/workflows/tests.yml)
[![License](https://poser.pugx.org/alphasnow/aliyun-oss-laravel/license)](https://packagist.org/packages/alphasnow/aliyun-oss-laravel)
[![Coverage Status](https://coveralls.io/repos/github/alphasnow/aliyun-oss-laravel/badge.svg?branch=4)](https://coveralls.io/github/alphasnow/aliyun-oss-laravel?branch=4)

[![Banner](https://banners.beyondco.de/Aliyun%20OSS%20Laravel.png?theme=light&packageManager=composer+require&packageName=alphasnow%2Faliyun-oss-laravel&pattern=architect&style=style_1&description=Alibaba+Cloud+Object+Storage+Service+For+Laravel&md=1&showWatermark=1&fontSize=100px&images=https%3A%2F%2Flaravel.com%2Fimg%2Flogomark.min.svg)](https://github.com/alphasnow/aliyun-oss-laravel)

这个包是封装 [aliyun-oss-flysystem](https://github.com/alphasnow/aliyun-oss-flysystem) 到 Laravel 来作为 Storage 使用.  
如果需要客户端直传, 可使用 Web服务端签名直传OSS扩展包 [aliyun-oss-appserver](https://github.com/alphasnow/aliyun-oss-appserver)  

## 版本兼容

| laravel      | aliyun-oss-laravel | driver | readme |
|:-------------|:-------------------|:-------|:-------|
| \>=5.5,\<9.0 | ^3.0               | aliyun | [readme](https://github.com/alphasnow/aliyun-oss-laravel/blob/3.x/README-CN.md) |
| \>=9.0       | ^4.0               | oss    | [readme](https://github.com/alphasnow/aliyun-oss-laravel/blob/4.x/README-CN.md) |

## 安装依赖
1. 通过composer管理您的项目依赖，可以在你的项目根目录运行：  
    ```bash
    composer require alphasnow/aliyun-oss-laravel
    ```
    然后通过`composer install`安装依赖。
2. 修改环境配置 `.env`
    ```env
    OSS_ACCESS_KEY_ID=<必填, 阿里云的AccessKeyId>
    OSS_ACCESS_KEY_SECRET=<必填, 阿里云的AccessKeySecret>
    OSS_BUCKET=<必填, 对象存储的Bucket>
    OSS_ENDPOINT=<必填, 对象存储的Endpoint>
    ```
3. (可选) 修改文件配置 `config/filesystems.php`
    ```php
    "default" => env("FILESYSTEM_DRIVER", "oss"),
    // ...
    "disks"=>[
        // ...
        "oss" => [
            "driver"            => "oss",
            "access_key_id"     => env("OSS_ACCESS_KEY_ID"),           // 必填, 阿里云的AccessKeyId
            "access_key_secret" => env("OSS_ACCESS_KEY_SECRET"),       // 必填, 阿里云的AccessKeySecret
            "bucket"            => env("OSS_BUCKET"),                  // 必填, 对象存储的Bucket, 示例: my-bucket
            "endpoint"          => env("OSS_ENDPOINT"),                // 必填, 对象存储的Endpoint, 示例: oss-cn-shanghai.aliyuncs.com
            "internal"          => env("OSS_INTERNAL", null),          // 选填, 内网上传地址,填写即启用 示例: oss-cn-shanghai-internal.aliyuncs.com
            "domain"            => env("OSS_DOMAIN", null),            // 选填, 绑定域名,填写即启用 示例: oss.my-domain.com
            "is_cname"          => env("OSS_CNAME", false),            // 选填, 若Endpoint为自定义域名，此项要为true，见：https://github.com/aliyun/aliyun-oss-php-sdk/blob/572d0f8e099e8630ae7139ed3fdedb926c7a760f/src/OSS/OssClient.php#L113C1-L122C78
            "prefix"            => env("OSS_PREFIX", ""),              // 选填, 统一存储地址前缀
            "use_ssl"           => env("OSS_SSL", false),              // 选填, 是否使用HTTPS
            "throw"             => env("OSS_THROW", false),            // 选填, 是否抛出引起错误的异常,默认出现错误时,不抛出异常仅返回false
            "signatureVersion"  => env("OSS_SIGNATURE_VERSION", "v1"), // 选填, 选择使用v1或v4签名版本
            "region"            => env("OSS_REGION", ""),              // 选填, 仅在使用v4签名版本时启用, 示例: cn-shanghai
            "options"           => [],                                 // 选填, 添加全局配置参数, 示例: [\OSS\OssClient::OSS_CHECK_MD5 => false]
            "macros"            => []                                  // 选填, 添加自定义Macro, 示例: [\App\Macros\ListBuckets::class, \App\Macros\CreateBucket::class]
        ],
        // ...
    ]
    ```

## 快速使用
```php
use Illuminate\Support\Facades\Storage;
$storage = Storage::disk("oss");
```

### 使用Storage方法
#### 写入
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

#### 读取
```php
Storage::disk("oss")->url("dir/path/file.txt");
Storage::disk("oss")->temporaryUrl("dir/path/file.txt", \Carbon\Carbon::now()->addMinutes(30));

Storage::disk("oss")->get("dir/path/file.txt"); 

Storage::disk("oss")->exists("dir/path/file.txt"); 
Storage::disk("oss")->size("dir/path/file.txt"); 
Storage::disk("oss")->lastModified("dir/path/file.txt");
```

#### 删除
```php
Storage::disk("oss")->delete("dir/path/file.txt");
Storage::disk("oss")->delete(["dir/path/file1.txt", "dir/path/file2.txt"]);
```

#### 文件操作
```php
Storage::disk("oss")->copy("dir/path/file.txt", "dir/path/file_new.txt");
Storage::disk("oss")->move("dir/path/file.txt", "dir/path/file_new.txt");
Storage::disk("oss")->rename("dir/path/file.txt", "dir/path/file_new.txt");
```

#### 文件夹操作
```php
Storage::disk("oss")->makeDirectory("dir/path"); 
Storage::disk("oss")->deleteDirectory("dir/path");

Storage::disk("oss")->files("dir/path");
Storage::disk("oss")->allFiles("dir/path");

Storage::disk("oss")->directories("dir/path"); 
Storage::disk("oss")->allDirectories("dir/path"); 
```

### 使用 Macro
#### 默认方法
```php
Storage::disk("oss")->appendObject("dir/path/news.txt", "The first line paragraph.", 0);
Storage::disk("oss")->appendObject("dir/path/news.txt", "The second line paragraph.", 25);
Storage::disk("oss")->appendObject("dir/path/news.txt", "The last line paragraph.", 51);

Storage::disk("oss")->appendFile("dir/path/file.zip", "dir/path/file.zip.001", 0);
Storage::disk("oss")->appendFile("dir/path/file.zip", "dir/path/file.zip.002", 1024);
Storage::disk("oss")->appendFile("dir/path/file.zip", "dir/path/file.zip.003", 1024);

Storage::disk("oss")->processObject("dir/path/image.jpg", "image/resize,l_1000");
```

#### 自定义 Macro
1. 开发 Macro
    ```php
    namespace App\Macros;
    use AlphaSnow\LaravelFilesystem\Aliyun\Macros\AliyunMacro;
    
    class ListBuckets implements AliyunMacro
    {
        // ... 
    }
    ```
    参考实例代码: [AppendObject.php](https://github.com/alphasnow/aliyun-oss-laravel/blob/4.5.0/src/Macros/AppendObject.php)
2. 修改配置
    ```php
    [
        "macros" => [\App\Macros\ListBuckets::class]
    ]
    ```
3. 使用 Macro
    ```php
    Storage::disk("oss")->listBuckets()
    ```

### 使用 OssClient
```php
use AlphaSnow\LaravelFilesystem\Aliyun\OssClientAdapter;

$adapter = new OssClientAdapter(Storage::disk("oss"));
$adapter->client()->appendObject($adapter->bucket(), $adapter->path("dir/path/file.txt"), "contents", 0, $adapter->options(["visibility" => "private"]));
```

## 文档
[阿里云 对象存储 OSS文档](https://help.aliyun.com/zh/oss/)

## 问题
如使用中遇到问题，[提交 Issue](https://github.com/alphasnow/aliyun-oss-laravel/issues)

## 贡献者
<a href="https://github.com/alphasnow/aliyun-oss-laravel/graphs/contributors">
  <img src="https://contrib.rocks/image?repo=alphasnow/aliyun-oss-laravel" />
</a>

## Star
[![Star History Chart](https://api.star-history.com/svg?repos=alphasnow/aliyun-oss-laravel&type=Timeline)](https://star-history.com/#alphasnow/aliyun-oss-laravel&Timeline)

## 授权
[MIT](LICENSE)