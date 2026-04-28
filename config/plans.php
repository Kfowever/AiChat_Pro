<?php

return [
    [
        'id' => 1,
        'name' => 'Free',
        'display_name' => '免费版',
        'price' => 0.00,
        'quota_amount' => 1.00,
        'quota_period' => 'monthly',
        'features' => [
            '每月 $1.00 额度',
            '基础模型访问',
            '标准响应速度',
        ],
        'sort_order' => 1,
    ],
    [
        'id' => 2,
        'name' => 'Lite',
        'display_name' => 'Lite 版',
        'price' => 5.00,
        'quota_amount' => 8.00,
        'quota_period' => 'monthly',
        'features' => [
            '每月 $8.00 额度',
            '全部模型访问',
            '优先响应速度',
            '文件上传支持',
        ],
        'sort_order' => 2,
    ],
    [
        'id' => 3,
        'name' => 'Pro',
        'display_name' => 'Pro 版',
        'price' => 20.00,
        'quota_amount' => 40.00,
        'quota_period' => 'monthly',
        'features' => [
            '每月 $40.00 额度',
            '全部模型访问',
            '最高优先响应',
            '文件上传支持',
            '深度思考模式',
            '联网搜索',
        ],
        'sort_order' => 3,
    ],
];
