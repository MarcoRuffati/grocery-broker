# Contributing

Forks and improvements are welcome. Development of the original live-data integration is paused; maintenance or review turnaround is not guaranteed.

Start with the README demo. Keep changes focused and describe the problem, resulting behaviour and verification. Useful contributions include internationalization, authorized local source integrations and improvements to quantity or promotion handling.

Run `php tests/run.php`, PHP syntax checks and `node --check public/assets/app.js`. With the initial demo server running, run `python3 tests/http_checks.py`. UI changes also need a browser check at desktop and mobile widths.

Never commit personal databases, credentials, caches, logs or third-party assets without appropriate rights. Use fictional stores and synthetic fixtures in public tests. Describe source permissions and territorial coverage when proposing a connector, without embedding account credentials.

Original project materials use the MIT licence. By contributing original materials, you agree to license them under MIT. Identify any third-party components and their licences separately. Preserve existing copyright and licence notices; distinguish your changes from the original project.
