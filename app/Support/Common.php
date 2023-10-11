<?php

namespace App\Support;

use Illuminate\Support\Facades\Storage;

class Common
{
    /*获取上传附件的函数 没有就显示默认值*/
    public static function getCoverPath($imageUrl, $defaultImageUrl)
    {
        // 如果传入的图片链接为空，则返回默认图片链接
        if (empty($imageUrl)) {
            return asset($defaultImageUrl);
        }
        // 获取传入图片链接的HTTP状态码
        $storageUrl = config('app.url');
        $path = ltrim($storageUrl, '/');
        $url = $path . Storage::url($imageUrl);
        //关闭https证书校验
        stream_context_set_default([
            'ssl' => [
                'verify_host' => false,
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);
        $headers = get_headers($url);
        $statusCode = substr($headers[0], 9, 3);

        // 如果HTTP状态码不是2xx，说明传入图片链接无效，则返回默认图片链接
        if ($statusCode < 200 || $statusCode >= 300) {
            return asset($defaultImageUrl);
        }

        // 如果传入图片链接有效，则直接返回该链接
        return Storage::url($imageUrl);
    }

    /**
     * 环比
     */
    public static function growth($nowValue, $lastValue)
    {
        if ($lastValue == 0 && $nowValue == 0) return 0;
        if ($lastValue == 0) return bcmul((string)$nowValue, '100', 2);
        if ($nowValue == 0) return bcdiv(bcsub($nowValue, $lastValue, 2), $lastValue, 2) * 100;
        return bcmul(bcdiv((bcsub($nowValue, $lastValue, 2)), $lastValue, 2), 100, 2);
    }

    /**
     * 计算百分比
     */
    public static function percent($nowValue, $lastValue)
    {
        if ($lastValue == 0 && $nowValue == 0) return 0;
        if ($lastValue == 0) return bcmul($nowValue, 100, 2);
        if ($nowValue == 0) return bcdiv($nowValue, $lastValue, 2) * 100;
        return bcmul(bcdiv($nowValue, $lastValue, 2), 100, 0);
    }
}