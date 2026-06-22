<?php

return [
    'triggers' => [
        'lead.created' => [
            'label' => 'ایجاد لید',
            'entity' => 'lead',
        ],
        'lead.stage_changed' => [
            'label' => 'تغییر مرحله لید',
            'entity' => 'lead',
        ],
        'lead.converted' => [
            'label' => 'تبدیل لید به معامله',
            'entity' => 'lead',
        ],
        'deal.created' => [
            'label' => 'ایجاد معامله',
            'entity' => 'deal',
        ],
        'deal.stage_changed' => [
            'label' => 'تغییر مرحله معامله',
            'entity' => 'deal',
        ],
    ],

    'condition_fields' => [
        'lead' => [
            'marketing_stage_id' => ['label' => 'مرحله بازاریابی', 'type' => 'stage'],
            'source' => ['label' => 'منبع', 'type' => 'string'],
            'campaign_id' => ['label' => 'کمپین', 'type' => 'number'],
            'score' => ['label' => 'امتیاز', 'type' => 'number'],
            'assigned_to' => ['label' => 'مسئول', 'type' => 'user'],
            'assignee_is_empty' => ['label' => 'بدون مسئول', 'type' => 'boolean'],
            'to_stage_id' => ['label' => 'مرحله مقصد', 'type' => 'stage'],
        ],
        'deal' => [
            'pipeline_stage_id' => ['label' => 'مرحله فروش', 'type' => 'stage'],
            'amount' => ['label' => 'مبلغ', 'type' => 'number'],
            'assigned_to' => ['label' => 'مسئول', 'type' => 'user'],
            'assignee_is_empty' => ['label' => 'بدون مسئول', 'type' => 'boolean'],
            'to_stage_id' => ['label' => 'مرحله مقصد', 'type' => 'stage'],
        ],
    ],

    'operators' => [
        'equals' => 'برابر با',
        'not_equals' => 'مخالف',
        'is_empty' => 'خالی است',
        'is_not_empty' => 'خالی نیست',
        'in' => 'یکی از',
        'gte' => 'بزرگ‌تر یا مساوی',
        'lte' => 'کوچک‌تر یا مساوی',
    ],

    'actions' => [
        'assign_user' => [
            'label' => 'تخصیص به کاربر',
            'params' => ['user_id' => ['type' => 'user', 'label' => 'کاربر']],
        ],
        'assign_round_robin' => [
            'label' => 'تخصیص چرخشی',
            'params' => ['user_ids' => ['type' => 'users', 'label' => 'کاربران']],
        ],
        'set_follow_up_reminder' => [
            'label' => 'تنظیم یادآور پیگیری',
            'params' => [
                'offset_days' => ['type' => 'number', 'label' => 'بعد از (روز)', 'default' => 1],
                'offset_hours' => ['type' => 'number', 'label' => 'بعد از (ساعت)', 'default' => 0],
            ],
        ],
        'create_task' => [
            'label' => 'ایجاد تسک',
            'params' => [
                'title' => ['type' => 'string', 'label' => 'عنوان'],
                'description' => ['type' => 'string', 'label' => 'توضیح', 'optional' => true],
                'assignee_id' => ['type' => 'user', 'label' => 'مسئول', 'optional' => true],
                'due_offset_days' => ['type' => 'number', 'label' => 'مهلت (روز)', 'default' => 1],
            ],
        ],
        'send_notification' => [
            'label' => 'ارسال اعلان',
            'params' => [
                'title' => ['type' => 'string', 'label' => 'عنوان'],
                'subtitle' => ['type' => 'string', 'label' => 'زیرعنوان', 'optional' => true],
                'notify' => ['type' => 'select', 'label' => 'گیرنده', 'options' => ['assignee' => 'مسئول', 'actor' => 'کاربر انجام‌دهنده']],
            ],
        ],
        'send_sms' => [
            'label' => 'ارسال پیامک',
            'requires_module' => 'mod-sms',
            'params' => [
                'message' => ['type' => 'text', 'label' => 'متن پیامک'],
            ],
        ],
    ],

    'sms_placeholders' => [
        '{{name}}' => 'نام',
        '{{phone}}' => 'تلفن',
        '{{company}}' => 'شرکت',
        '{{stage}}' => 'مرحله',
    ],
];
