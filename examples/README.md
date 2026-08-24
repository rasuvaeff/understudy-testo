# Examples

| Script | Shows | Needs server? |
|---|---|---|
| `lifecycle.php` | The adapter lifecycle on a simulated pipeline: fulfilled expectation keeps a pass, unmet expectation fails it after the fact, a failing body keeps its own failure | No |

Run from the package root:

```bash
docker run --rm -v "$PWD":/app -w /app composer:2 php examples/lifecycle.php
```

In a real suite none of this plumbing is visible: register
`UnderstudyPlugin` in your `testo.php` (see README) and write plain
understudy code — verification and cleanup happen after every test.
