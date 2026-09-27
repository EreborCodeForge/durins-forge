# fixture-worker

Created with `durin new` preset **worker** (job / non-HTTP).

## Next steps

```bash
composer install
cp .env.example .env
# bind a real JobTransport in JobKernel::boot()
php vendor/bin/job-worker
# or: composer job:work
```

This app does **not** use Eregion. See Mithril job-worker docs and Durin SPEC-DX-017.

Structure:

- `src/JobKernel.php`
- `src/Application`
- `src/Infrastructure`
- `src/Jobs`
- `config`
- `tests`
