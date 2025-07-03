# DynamicContext - Usage Documentation

DynamicContext, template strings ve arrays üzerinde dinamik değişken değiştirme ve fonksiyon uygulama işlemlerini gerçekleştiren güçlü bir PHP sınıfıdır.

## Temel Kullanım

```php


$context = [
    'user.name' => 'John Doe',
    'user.email' => 'john@example.com',
    'products.0.name' => 'Laptop',
    'products.0.price' => 1500,
    'products.1.name' => 'Mouse',
    'products.1.price' => 25
];

$template = 'Merhaba {{user.name}}, email adresiniz: {{user.email}}';
$result = DynamicContext::replace($template, $context);
// Sonuç: "Merhaba John Doe, email adresiniz: john@example.com"
```

## Yapılandırma

DynamicContext, placeholder'ları config dosyasından okur:

```php
// config/oo-auto-weave.php
return [
    'placeholders' => [
        'variable' => [
            'start' => '{{',
            'end' => '}}'
        ],
        'function' => [
            'start' => '@@',
            'end' => '@@'
        ]
    ]
];
```

### Basit Değişkenler

```php
$context = ['name' => 'Ali', 'age' => 25];
$template = 'İsim: {{name}}, Yaş: {{age}}';
$result = DynamicContext::replace($template, $context);
// Sonuç: "İsim: Ali, Yaş: 25"
```

### Noktalı Notation

```php
 $context = [
        'user.profile.name' => 'Ayşe',
        'user.profile.city' => 'İstanbul',
        "test"=>[
            "merhaba"=>"12"
        ]
    ];
    $template = '{{user.profile.name}} - {{user.profile.city}} - {{test.merhaba}}';
    $result = DynamicContext::replace($template, $context);
// Sonuç: "Ayşe - İstanbul - 12"
```

### Tek Değişken Template

Template tamamen tek bir değişken ise, o değişkenin orijinal türü korunur:

```php
$context = ['users' => ['Ali', 'Veli', 'Deli']];
$template = '{{users}}';
$result = DynamicContext::replace($template, $context);
// Sonuç: ['Ali', 'Veli', 'Deli'] (array olarak)
```

## Wildcard Kullanımı

### Basit Wildcard

```php
$context = [
    'users.0.name' => 'Ali',
    'users.1.name' => 'Veli',
    'users.2.name' => 'Ahmet'
];
$template = '{{users.*.name}}';
$result = DynamicContext::replace($template, $context);
// Sonuç: ['Ali', 'Veli', 'Ahmet']
```

### Çoklu Wildcard

```php
$context = [
    'departments.0.users.0.name' => 'Ali',
    'departments.0.users.1.name' => 'Veli',
    'departments.1.users.0.name' => 'Ayşe',
    'departments.1.users.1.name' => 'Fatma'
];
$template = '{{departments.*.users.*.name}}';
$result = DynamicContext::replace($template, $context);
// Sonuç: ['Ali', 'Veli', 'Ayşe', 'Fatma']
```

### İç İçe Array Wildcard

```php
$context = [
    'managers.0.additional_emails.0.name' => 'work@example.com',
    'managers.0.additional_emails.1.name' => 'personal@example.com',
    'managers.1.additional_emails.0.name' => 'admin@example.com'
];
$template = '{{managers.*.additional_emails.*.name}}';
$result = DynamicContext::replace($template, $context);
// Sonuç: ['work@example.com', 'personal@example.com', 'admin@example.com']
```


### Tek Değişken vs Çoklu Değişken

```php
// Tek değişken → Orijinal tür korunur
$result = DynamicReplacer::replace('{{users}}', $context);
// Sonuç: ['Ali', 'Veli'] (array)

// String içinde değişken → JSON string
$result = DynamicReplacer::replace('Kullanıcılar: {{users}}', $context);
// Sonuç: 'Kullanıcılar: ["Ali","Veli"]' (string)
```
