<?php



return [

    'sales_stages' => [

        ['name' => 'سرنخ', 'sort_order' => 1, 'color' => '#7367F0', 'type' => 'sales', 'is_won' => false, 'is_lost' => false],

        ['name' => 'مذاکره', 'sort_order' => 2, 'color' => '#00CFE8', 'type' => 'sales', 'is_won' => false, 'is_lost' => false],

        ['name' => 'پیشنهاد', 'sort_order' => 3, 'color' => '#FF9F43', 'type' => 'sales', 'is_won' => false, 'is_lost' => false],

        ['name' => 'مالی', 'sort_order' => 4, 'color' => '#9C27B0', 'type' => 'sales', 'is_won' => false, 'is_lost' => false],

        ['name' => 'برنده', 'sort_order' => 5, 'color' => '#28C76F', 'type' => 'sales', 'is_won' => true, 'is_lost' => false],

        ['name' => 'باخته', 'sort_order' => 6, 'color' => '#EA5455', 'type' => 'sales', 'is_won' => false, 'is_lost' => true],

    ],

    'marketing_stages' => [

        ['name' => 'بازدید', 'sort_order' => 1, 'color' => '#7367F0', 'type' => 'marketing', 'is_won' => false, 'is_lost' => false],

        ['name' => 'علاقه‌مند', 'sort_order' => 2, 'color' => '#00CFE8', 'type' => 'marketing', 'is_won' => false, 'is_lost' => false],

        ['name' => 'واجد شرایط', 'sort_order' => 3, 'color' => '#FF9F43', 'type' => 'marketing', 'is_won' => false, 'is_lost' => false],

        ['name' => 'آماده فروش', 'sort_order' => 4, 'color' => '#28C76F', 'type' => 'marketing', 'is_won' => false, 'is_lost' => false],

        ['name' => 'نامرتبط', 'sort_order' => 5, 'color' => '#EA5455', 'type' => 'marketing', 'is_won' => false, 'is_lost' => true],

    ],

    // backward compatibility

    'default_stages' => [

        ['name' => 'سرنخ', 'sort_order' => 1, 'color' => '#7367F0', 'type' => 'sales'],

        ['name' => 'مذاکره', 'sort_order' => 2, 'color' => '#00CFE8', 'type' => 'sales'],

        ['name' => 'پیشنهاد', 'sort_order' => 3, 'color' => '#FF9F43', 'type' => 'sales'],

        ['name' => 'برنده', 'sort_order' => 4, 'color' => '#28C76F', 'type' => 'sales'],

        ['name' => 'باخته', 'sort_order' => 5, 'color' => '#EA5455', 'type' => 'sales'],

    ],

    'min_stages' => 2,

];

