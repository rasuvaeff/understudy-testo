# rasuvaeff/understudy-testo

Testo-адаптер для [rasuvaeff/understudy](https://github.com/rasuvaeff/understudy) —
библиотеки test double, где настроенный вызов — это настоящий вызов:
`when(fn () => $repo->find(123))->returns($book)`.

Плагин завершает каждый тест служебной работой understudy за вас:

- **verify после успеха** — после успешного тела теста проверяются все
  `expect()`. Ожидание, которое код под тестом не выполнил, превращает
  «зелёный» тест в упавший;
- **исходная ошибка главнее** — после упавшего или пропущенного тела ничего
  не верифицируется, поэтому адаптер никогда не маскирует настоящую ошибку;
- **reset всегда** — контекст сбрасывается после каждого теста, в `finally`.
  Один тест не может протечь дублем, ожиданием или стабом в следующий.

> Используете AI-ассистента? [llms.txt](llms.txt) — компактный API-справочник,
> который он может загрузить вместо догадок.

## Требования

- PHP 8.3 – 8.5
- `rasuvaeff/understudy` (`^0.1`)
- `testo/testo` (`^0.10.39`)

## Установка

```bash
composer require --dev rasuvaeff/understudy-testo
```

## Использование

Зарегистрируйте плагин на тех suite, где используются дубли:

```php
// testo.php
use Rasuvaeff\Understudy\Testo\UnderstudyPlugin;
use Testo\Application\Config\ApplicationConfig;
use Testo\Application\Config\SuiteConfig;

return new ApplicationConfig(
    src: ['src'],
    suites: [
        new SuiteConfig(
            name: 'Unit',
            location: ['tests'],
            plugins: [new UnderstudyPlugin()],
        ),
    ],
);
```

Дальше тесты пишутся так, как это документирует ядро, — без ручной уборки:

```php
<?php

declare(strict_types=1);

namespace App\Tests;

use function Rasuvaeff\Understudy\expect;
use function Rasuvaeff\Understudy\when;

use App\Contract\BookRepository;
use Rasuvaeff\Understudy\Understudy;
use Testo\Assert;
use Testo\Test;

#[Test]
final class CheckoutTest
{
    public function chargesForTheCart(): void
    {
        $books = Understudy::for(BookRepository::class);
        when(static fn() => $books->find(7))->returns($expected = new Book(7));

        $service = new Checkout($books);
        $receipt = $service->charge(cart: [7]);

        Assert::same($receipt->total, $expected->price);
        expect(static fn() => $books->find(7)); // ровно один раз — проверено за вас
    }
}
```

Если сервис так и не вызвал `find(7)`, тест падает после тела — с отчётом о
невыполненном ожидании, а не с молчаливым «зелёным».

### Strict stubs

```php
new UnderstudyPlugin(strictStubs: true)
```

Настроенный, но ни разу не вызванный стаб тоже валит тест — прочтение Mockito:
«зачем настраивали, если он не нужен?». По умолчанию выключено; точечная
строгость на конкретный дубль доступна из ядра через `Understudy::strict($double)`
независимо от этой настройки.

## Что попадает в отчёт

На успешном тесте верификация считается ещё одной проверкой теста: метрика
`assertions` увеличивается на единицу, а в собранный `TestState` дописывается
запись «expectations verified». Провал верификации фиксируется там же и
сообщается как причина падения теста.

Тест, у которого единственная проверка — ожидание understudy, не становится
`risky`. Testo объявляет прошедший тест рискованным, когда тот не записал ни
одного ассерта, и решает это раньше, чем адаптер успевает внести верификацию —
поэтому адаптер снимает вердикт, если его запись в истории единственная. Тесты,
которые ассертят сами, сохраняют тот вердикт, который заслужили.

Одно место, где её не видно, — блок `assert-history`, который печатает Testo.
Коллектор рендерит этот текст до возврата, а адаптер работает снаружи
коллектора, поэтому к моменту рендера записи ещё не существует. Счётчик и
приложенный к результату `TestState` её несут, печатаемая история — нет.

## API

| Член | Назначение |
|---|---|
| `UnderstudyPlugin` | Регистрирует интерцептор на suite; `strictStubs` по умолчанию выключен |
| `UnderstudyInterceptor` | Verify-after-success, reset-in-`finally`; регистрируется плагином |

Всё остальное — `for()`, `when()`, `expect()`, `verify()`, матчеры, forwarding,
`wire()` — относится к [rasuvaeff/understudy](https://github.com/rasuvaeff/understudy)
и документировано там. Этот пакет не добавляет собственных операций.

## Изоляция Fiber

Контексты рантайма ядра локальны для fiber, поэтому тесты, приостанавливающие
fibers, держат свои дубли изолированно, а `reset()` чистит только текущий
контекст. Адаптер не копирует и не подменяет состояние процесса.

## Примеры

См. [`examples/`](examples/README.md).

## Разработка

На хосте нет PHP/Composer — всё запускается через Docker:

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 composer build
```

Или через Make: `make build`, `make cs-fix`, `make psalm`, `make test`.

## Лицензия

[BSD-3-Clause](LICENSE.md)
