# Changelog

## 0.2.0

- First public release: translations (English, Spanish). Report titles, labels, KPI and
  chart labels, presets, warnings, error messages and CSV headers go through Kimai's
  translator; Kimai's own strings are reused where they fit. Weekday names, dates and
  numbers follow the user's locale.
- CSV file names are now in English (`kimai_hours_summary_<from>_<to>.csv`,
  `kimai_work_contracts.csv`, `kimai_inactive_customers_<n>d.csv`).
- Removed references to the in-house setup the plugin was written for.
- MIT license, README, contributing guide and CI linting (php-cs-fixer, PHPStan level 9).

## 0.1.0

- First release, targeting Kimai 2.66 (`extra.kimai.require` 26600) and PHP 8.2+.
- **Hours summary** (`management_reports`: team leads, admins): filters, KPI cards, six
  charts, ranking tables with % of total and a CSV of the detail rows. Team leads only see
  users Kimai lets them see.
- **Work contracts** (`management_reports_admin`: admins): daily and weekly hours per
  user, contract type, and a warning when Kimai would compute 0 expected hours. CSV.
- **Inactive customers** (`management_reports_admin`): customers without hours in the last
  30/60/90/180 days or never. CSV. Inactive projects stay in Kimai's own report.
