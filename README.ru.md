# rasuvaeff/understudy-testo

[![Latest Stable Version](https://poser.pugx.org/rasuvaeff/understudy-testo/v)](https://packagist.org/packages/rasuvaeff/understudy-testo)
[![Total Downloads](https://poser.pugx.org/rasuvaeff/understudy-testo/downloads)](https://packagist.org/packages/rasuvaeff/understudy-testo)
[![Build](https://github.com/rasuvaeff/understudy-testo/actions/workflows/build.yml/badge.svg)](https://github.com/rasuvaeff/understudy-testo/actions/workflows/build.yml)
[![Static analysis](https://github.com/rasuvaeff/understudy-testo/actions/workflows/static-analysis.yml/badge.svg)](https://github.com/rasuvaeff/understudy-testo/actions/workflows/static-analysis.yml)
[![Psalm level](https://img.shields.io/badge/psalm-level_1-blue.svg)](https://github.com/rasuvaeff/understudy-testo/actions/workflows/static-analysis.yml)
[![PHP](https://img.shields.io/packagist/dependency-v/rasuvaeff/understudy-testo/php)](https://packagist.org/packages/rasuvaeff/understudy-testo)
[![License](https://img.shields.io/badge/license-BSD--3--Clause-blue.svg)](LICENSE.md)
[English version](README.md)

Testo-адаптер для [rasuvaeff/understudy](https://github.com/rasuvaeff/understudy) —
библиотеки test double, где настроенный вызов — это настоящий вызов:
`when(fn () => $repo->find(123))->returns($book)`.

Плагин завершает каждый обычный тест Testo служебной работой understudy за вас:

- **verify после успеха** — после успешного тела теста проверяются все
  `expect()`. Ожидание, которое код под тестом не выполнил, превращает
  «зелёный» тест в упавший;
- **исходная ошибка главнее** — после упавшего или пропущенного тела ничего
  не верифицируется, поэтому адаптер никогда не маскирует настоящую ошибку;
- **reset всегда** — контекст сбрасывается после каждого теста, в `finally`.
  Один тест не может протечь дублем, ожиданием или стабом в следующий.

Верификация — только для обычных тестов `#[Test]`. Кейсы `#[TestInline]` и
бенчмарки не верифицируются: inline-кейс задуман как чистая детерминированная
таблица без setup'а, за который надо отвечать, а бенчмарк платил бы за
верификацию на каждой итерации. Дубли создавайте в обычных тестах. Сброс так
не сужен: дубли теста любого вида отбрасываются в его конце, поэтому
inline-кейс не может передать остаток следующему тесту.

> Используете AI-ассистента? [llms.txt](llms.txt) — компактный API-справочник,
> который он может загрузить вместо догадок.

## Требования

- PHP 8.3 – 8.5
- `rasuvaeff/understudy` (`^0.1`)
- `testo/testo` (`^0.10.42`)

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
fibers, держат свои дубли изолированно. Верификация намеренно шире: `verifyAll()`
и `reset()` достают до каждого контекста, куда тест положил дубли, включая тот,
которым владеет тело `#[RunInFiber]` — этот интерцептор в нём никогда не стоит, и
пока ядро не охватывало их все, неисполненное `expect()` в таком тесте проходило
молча. Сам адаптер не копирует и не подменяет состояние процесса.

## Примеры

См. [`examples/`](examples/README.md).

## Семейство understudy

| Пакет | Что это |
|---|---|
| [rasuvaeff/understudy](https://github.com/rasuvaeff/understudy) | Движок: дубли, матчеры, ожидания, верификация. |
| **rasuvaeff/understudy-testo** *(этот пакет)* | Testo-адаптер — верификация и сброс вокруг каждого теста. |
| [rasuvaeff/understudy-phpunit](https://github.com/rasuvaeff/understudy-phpunit) | Адаптер для PHPUnit и Pest — то же самое, через трейт. |
| [rasuvaeff/understudy-psalm](https://github.com/rasuvaeff/understudy-psalm) | Psalm-плагин — спецификации с матчерами и диагностики ошибок. |

## Разработка

На хосте нет PHP/Composer — всё запускается через Docker:

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 composer build
```

Или через Make: `make build`, `make cs-fix`, `make psalm`, `make test`.

## Лицензия

[BSD-3-Clause](LICENSE.md)
