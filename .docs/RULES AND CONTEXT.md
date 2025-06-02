## 🧠 Context Extractor & Rule Engine

### 🔍 Context Extractor Nedir?

`OOAutoWeave`, bir modelin içeriğini otomasyon sistemine aktarabilmek için özel bir **Context Extractor** altyapısı sağlar. Bu sistem, modelin direkt alanlarını, ilişkilerini ve JSON alanlarını **dot notation** formatında düzleştirerek tek bir `context` array’i haline getirir.

#### Örnek Context:

```php
[
    'status' => 'active',
    'form_code' => 'F-12345',
    'r_brand.name' => 'BMW',
    'r_causer.r_managers.0.name' => 'Ahmet',
    'r_causer.r_managers.1.name' => 'Ayşe',
]
```

### 🧾 Rule Set Sistemi

ActionSet seviyesinde, bir dizi **kural** tanımlayarak tetiklenen işlemleri kontrol edebilirsiniz. Bu sayede yalnızca belirli koşullar sağlandığında action'lar çalıştırılır.

#### Desteklenen Operatörler:

* `=` eşittir
* `!=` eşit değildir
* `>` büyüktür
* `>=` büyük eşittir
* `<` küçüktür
* `<=` küçük eşittir
* `in` belirtilenlerden biri
* `not in` belirtilenler dışında

#### Örnek Kullanım:

```php
$rules = Rule::make()
    ->and('status', '=', 'active')
    ->and('r_brand.name', '=', 'BMW');

$isMatched = $rules->evaluateAgainst($context);
```

---

### 🔁 Placeholder (Değişken Yerine Koyma)

Her action içerisinde dinamik alanlar `"{{...}}"` şeklinde yazılarak context'e göre doldurulur.

#### Replace Örneği:

```php
$replaced = DataProcessor::replace([
    'form_code' => '{{form_code}}',
    'marka' => '{{r_brand.name}}',
    'gönderen yöneticiler' => '{{r_causer.r_managers.*.name}}',
], $context);
```

**Not:** `*.name` gibi çoklu veri yapıları, otomatik olarak virgülle birleştirilir (`implode(', ', ...)`).

---

### 🧪 Test Route Örneği

Aşağıdaki route ile hem rule eşleşmesini hem de context mapping işlemini test edebilirsiniz:

```php
    Route::get('context-extractor', function () {
        $model = \App\Models\User::find(2);
        $context = DataProcessor::extractContext($model, $model::filterableColumns(2));

        $rules = Rule::make()
            ->and('status', '=', 'active');

        $isMatched = $rules->evaluateAgainst($model, $model::filterableColumns(2));

        $replaced = DataProcessor::replace([
            'form_code' => '{{form_code}}',
            'marka' => '{{r_brand.name}}',
            'gönderen yöneticiler' => '{{r_causer.r_managers.*.name}}',
        ], $context);

        $context = \Illuminate\Support\Arr::undot($context);

        dd($isMatched, $context, $replaced);

    });


    Route::get('context-extractor-test', function () {

        $context = [
            'status' => 'active',
            'form_code' => 'F-12345',
            'r_brand.name' => 'BMW',
            'r_causer.r_managers.0.name' => 'Ahmet',
            'r_causer.r_managers.1.name' => 'Ayşe',
        ];

        // Rule test
        $rules = Rule::make()
            ->and('status', '=', 'active1')
            ->and('r_brand.name', '=', 'BMW');
        $isMatched = $rules->evaluateAgainst($context);

        // Replace test
        $replaced = DataProcessor::replace([
            'form_code' => '{{form_code}}',
            'marka' => '{{r_brand.name}}',
            'gönderen yöneticiler' => '{{r_causer.r_managers.*.name}}',
        ], $context);

        $undotted = Arr::undot($context);

        dd($isMatched, $undotted, $replaced);
    });
```

---

### ✅ ActionSet Rule Kontrolü

Aşağıdaki yapı, `status` alanı `active` ise `true_branch` action'larını, değilse `false_branch` action'larını çalıştırır:

```php
'action_set' => [
    'rules' => [
        [
            'columnKey' => 'status',
            'operator' => '=',
            'value' => 'active',
            'type' => 'and',
        ],
    ],
],
```

## 🧩 `FilterableColumnsProviderInterface` ve `HasFilterableColumns` Kullanımı

### 🎯 Amaç

`OOAutoWeave` sistemi içerisinde; trigger context’ini çıkarmak, rule değerlendirmesi yapmak ve dinamik parametreleri işlemek için **modelin alanlarını ve ilişkilerini tanımlanabilir hale getirmek** gerekir. İşte bu nedenle modelin, bu verileri dışa sunabilmesi için şu iki yapı kullanılır:

* `FilterableColumnsProviderInterface`
* `HasFilterableColumns` trait’i

---

### ✅ `FilterableColumnsProviderInterface`

Bu interface, modelin bir `defineFilterableColumns()` metodu içermesini zorunlu kılar. Amaç, sistemin modelden hangi alanları çıkarabileceğini ve kullanıcıya filtreleme veya koşul belirlemede hangi alanların sunulacağını belirlemektir.

```php
class User extends Authenticatable implements FilterableColumnsProviderInterface
```

Bu interface, şunu garanti eder:

```php
public static function defineFilterableColumns($deepLevel = 0, $currentLevel = 0);
```

---

### 🧬 `HasFilterableColumns` Trait

Bu trait, yukarıdaki metodu çalıştıracak yardımcı metodları içerir. Örneğin:

```php
User::filterableColumns(2);
```

Bu, `defineFilterableColumns(2)` metodunu çağırır ve modeli maksimum 2 seviye derinlikte analiz eder.

> Bu sayede hem `context` extraction sırasında, hem de kullanıcıya sunulan "kural oluşturma" arayüzlerinde hangi alanlar gösterilecek otomatikleşir.

---

### 🏗️ `defineFilterableColumns()` Ne Yapar?

Bu metot:

* Modelin temel alanlarını (`id`, `name`, `status`, `email` gibi) tanımlar.
* Belirtilen derinlik (`deepLevel`) parametresine göre **ilişkileri** de dahil eder.
* Her tanım; `columnKey`, `columnType`, `columnName`, `label` gibi yapılandırmalar içerir.

#### Örnek tanım:

```php
[
    'columnKey' => 'status',
    'columnType' => 'enum',
    'columnName' => 'status',
    'label' => 'Durum',
]
```

#### İlişki örneği:

```php
[
    'columnKey' => 'r_brands',
    'columnType' => 'relation_hasMany',
    'columnName' => 'r_brands',
    'label' => 'Markalar',
    'columnModelType' => Brand::class,
    'inner' => Brand::filterableColumns($deepLevel, $currentLevel + 1),
]
```

---

### 💡 Kullanım Senaryoları

#### 1. Rule Engine

```php
Rule::make()
    ->and('status', '=', 'active')
    ->and('r_brands.name', '=', 'BMW');
```

Burada `status` ve `r_brands.name` alanlarının `defineFilterableColumns()` içinde tanımlanmış olması gerekir.

#### 2. Context Çıkarma

```php
$context = DataProcessor::extractContext($model, $model::filterableColumns(2));
```

Bu sayede:

```php
[
    'status' => 'active',
    'r_brands.0.name' => 'BMW',
]
```

şeklinde **dot-notation** ile context çıkartılır.

#### 3. Değişken Yerine Koyma (Placeholder Replace)

```php
'title' => '{{r_brands.0.name}}'
```

gibi ifadeler otomatik olarak context’ten doldurulabilir hale gelir.

---

### 🎓 Sonuç

Bu yapı sayesinde:

* Kod yazmadan ilişkisel alanlar dahil tüm model özellikleri sistem tarafından tanınır.
* Otomasyon tetikleme, rule kontrolü ve action parametreleri tamamen **dinamik hale** gelir.
* Paket farklı modellerle çalışabilir çünkü her model kendi `defineFilterableColumns()` metodunu tanımlar.

