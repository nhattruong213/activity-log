### Cài đặt
1. Copy source của package vào path: *packages\nnt\activity-log*
2. Thêm package vào *composer.json*

        "prefer-stable": true,
        "repositories": [
                {
                    "type": "path",
                    "url": "./packages/nnt/activity-log/"
                }
            ]

3. Chạy lệnh sau để cài đặt package từ đường dẫn local:

        composer require nnt/activity-log

4. Publish cấu hình

        php artisan vendor:publish --provider="NNT\ActivityLog\ActivityLogServiceProvider" --tag="config"

        php artisan config:cache

5. Publish migration:

        php artisan vendor:publish --provider="NNT\ActivityLog\ActivityLogServiceProvider" --tag="migrations"

6. Chạy *php artisan migrate* để tạo bảng activity_logs trong DB

### Hướng dẫn dùng

#### Manually log
**Để log nhanh thì có thể sử dụng**

```php
activity_log()->log('Hello World!');
```

**Một số tính năng nâng cao:**
1. Set log_type column

    Sử dụng `logType` để đặt giá trị cột log_type
    ```php
    activity_log()   
        ->logType($eventName)
        ->log('Hello World!');
    ```
2. Set Subject column

    Sử dụng `performedOn` để đặt giá trị cột subject. Cột này sẽ lưu giá id và model của đối tượng cần log
    ```php
    activity_log()   
        ->performedOn($anEloquentModel)
        ->log('Hello World!');
    ```
3. Set Subject column

    Sử dụng `causedBy` để đặt giá trị cột causer. Cột này sẽ lưu giá id và model của user đang log.
    ```php
    activity_log()   
        ->causedBy($userEloquentModel)
        ->log('Hello World!');
    ```
    Mặc định nếu không truyền gì vào thì sẽ lấy user đang logged in.

    Nếu bỏ trống trường này thì sử dụng `causedByAnonymous`
    ```php
    activity_log()   
        ->causedByAnonymous()
        ->log('Hello World!');
    ```
4. Set before_value and after_value column

    Sử dung `beforeValue` và `afterValue` để set giá trị cho 2 cột này, param truyền vào là array, dữ liêu sẽ được json_encode trước khi lưu vào database
    ```php
    activity_log()   
        ->beforeValue($beforeValue)
        ->afterValue($afterValue)
        ->log('Hello World!');
    ```
5. Set description column

    Sử dụng `log` để set giá trị cho cột description. Phương thức này bắt buộc phải có để save data
    ```php
    activity_log()
        ->log('Hello World!');
    ```
6. Set created_at column

    Sử dụng `createdAt` để set giá trị cho cột created_at. Param truyền vào là Carbon Object
    ```php
    activity_log()
        ->createdAt($timestamp)
        ->log('Hello World!');
    ```
7. Để disable log cho request hiện tại thì sử dụng
    ```php
    activity_log()->disableLogging();
    ```
8. Để enable log cho request hiện tại thì sử dụng
    ```php
    activity_log()->enableLogging();
    ```
#### Model Event Log
Package này có thể sử dụng để autolog model event

Để sử dụng thì trong model thêm trait `LogsActivity`
```php
use ES\ActivityLog\Traits\LogsActivity;

class NewsItem extends Model
{
    use LogsActivity;
}
```
Mặc định là `['created', 'updated', 'deleted']`, nếu có sử dụng trait **SoftDelete** thì có thêm 1 event được hỗ trợ nữa là `['restored']` 

Để chỉ định những model event nào cần log thì setting bằng cách thêm `$recordEvents` trong model

     protected static $recordEvents = ['deleted'];

Nếu muốn chỉ định những attributes nào sẽ không kích hoạt sự kiện log khi nó thay đổi thì sử dụng

    protected static $ignoreChangedAttributes = ['text'];

Mặc định thì `updated_at` sẽ không được ignore, nên sẽ trigger khi update dữ liệu. Nếu muốn bỏ qua điều này thì bỏ nó vào trong `$ignoreChangedAttributes`

Nếu muốn loại bỏ các attributes ra khỏi giá trị log before and after value thì sử dụng 

    protected static $logAttributesToIgnore = ['password', 'updated_at'];

Để custom description thì sử dụng method `customActivityDescription()` trong model. Method này sẽ cho phép chỉnh sửa lại description trước khi lưu vào database

Ví dụ:
```php
public function customActivityDescription($model, string $eventName) {
    if ($model->wasChanged('password')) {
        return 'Password has changed';
    }
    return __("activity_log::messages.{$eventName}");
}
````

Để set cứng cột related_type thì thêm `$relatedType` trong model

     protected static $relatedType = 'User';

Nếu không set cứng giá trị này thì cột related_type sẽ được thêm vơi định dạng PascalCase và dùng số ít (Job, Customer,...)

#### Config Advanced
Có thể setting 1 số chức năng ở trong file config. Xem chi tiết ở file activity-log.php

