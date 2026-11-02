# Management Reports for Kimai

[![CI](https://github.com/moui/ManagementReportsBundle/actions/workflows/linting.yaml/badge.svg)](https://github.com/moui/ManagementReportsBundle/actions/workflows/linting.yaml)

A Kimai plugin that adds three management reports to Kimai's **Reporting** page: an
**hours summary** with KPIs, charts and concentration rankings for any date range and
filter, a **work contracts** overview that flags users whose expected hours Kimai would
compute as zero, and a list of **inactive customers**. Every report can be downloaded as
CSV. The plugin creates no database tables; it reads Kimai's data through Doctrine (DQL)
and one parameterized DBAL query.

## Features

| Report | Route | Permission | Default roles |
|---|---|---|---|
| Hours summary | `/{locale}/reporting/management/summary` | `management_reports` | team leads, admins, super admins |
| Work contracts | `/{locale}/reporting/management/contracts` | `management_reports_admin` | admins, super admins |
| Inactive customers | `/{locale}/reporting/management/inactive-customers` | `management_reports_admin` | admins, super admins |

### Hours summary

- **Filters:** date range (default: the last 30 days through today, plus presets for the
  last 30 days, this month, last month, this year and the last 180 days), users, customers,
  projects (shown as `Project [Customer]`) and activities, all searchable, and *Top N*
  (5–25, default 10) for the charts.
- **KPI cards:** hours, % billable, average hours per day (over calendar days, not working
  days), entries, active users, customers and projects, activities used, entries per user,
  and the share of the top customer.
- **Charts** (Kimai's own Chart.js macros): hours by customer and by project (top N plus
  "Others"), by user, billable vs non-billable, by activity (top N plus "Others") and by day
  of the week.
- **Ranking tables** of customers, projects and activities with their hours and share of
  the total. They follow the summary's filters and list every row; rows beyond the top N
  are dimmed. Use them to see how concentrated the work is.
- **CSV** of the detail rows: date, customer, project, activity, user, hours (decimal),
  billable, description.

Only **finished** timesheets count, and an entry belongs to its **local** date (Kimai's
`date` column), not its UTC start time. The detail rows are not repeated on the page:
download the CSV, or use Kimai's team timesheet list and export, which already filter and
paginate.

**Scope:** admins, super admins and users allowed to see all data see everyone. Anyone
else (typically team leads) sees the users Kimai's own rules allow: themselves, members of
the teams they lead and team-less regular users, the same check Kimai uses for its user
reports. The user filter only offers those users, ids outside the scope are ignored even
if submitted, and the CSV is scoped the same way. Disabled users are not offered in the
filter, but their historical hours still count.

### Work contracts

Every enabled, non-system user without `ROLE_ADMIN` or `ROLE_SUPER_ADMIN`: hours per
weekday (the `work_monday` … `work_sunday` preferences), the weekly total and the contract
type, largest weekly total first. A badge flags users with **daily hours but no contract
type** (Kimai then computes 0 expected hours, which breaks anything based on expected
working time, such as overtime or missing-hours checks) and users with **no working hours
at all**. CSV export.

### Inactive customers

Visible customers without a finished timesheet in the last 30, 60 (default), 90 or 180
days, or with none ever, longest inactive first, with a link to each customer. CSV export.
Inactive **projects** are not repeated here: Kimai's built-in *Inactive projects* report
covers them and is linked from the page.

## Requirements

- Kimai 2.66 or later
- PHP 8.2 or later

## Installation

Download the plugin and extract it, or clone it, into `var/plugins/` of your Kimai
installation. The directory must be named `ManagementReportsBundle`:

```bash
cd /opt/kimai/var/plugins/
git clone https://github.com/moui/ManagementReportsBundle.git
```

Then clear the cache:

```bash
cd /opt/kimai/
bin/console kimai:reload -n
```

The plugin has no database migrations, so no install command is needed.

**Docker:** mount or copy the plugin to `/opt/kimai/var/plugins/ManagementReportsBundle`
and reload as the web server user, otherwise the cache files end up owned by root and Kimai
cannot write them:

```bash
docker compose exec -u www-data kimai bin/console kimai:reload -n
```

## Configuration

There is nothing to configure. The reports appear on Kimai's **Reporting** page for users
with the permissions below.

## Permissions

| Permission | Default roles | Grants |
|---|---|---|
| `management_reports` | `ROLE_TEAMLEAD`, `ROLE_ADMIN`, `ROLE_SUPER_ADMIN` | The hours summary, scoped to the users the viewer may see |
| `management_reports_admin` | `ROLE_ADMIN`, `ROLE_SUPER_ADMIN` | The work contracts and inactive customers reports, which cover the whole organisation |

Both reports also require Kimai's `view_reporting` permission. Permissions can be granted
to other roles from Kimai's *Roles* screen.

## Console commands

None.

## Translations

The plugin ships with English (`en`, the fallback) and Spanish (`es`). Strings live in
`Resources/translations/` as XLIFF 1.2: `messages.*.xlf` for the report pages and CSV
headers, and `reporting.*.xlf` for the report titles on Kimai's Reporting page. Where Kimai
already has a fitting string (date, customer, user, hours…), the plugin uses Kimai's own
translation. Weekday names and number formats follow the user's locale.

To add a language, copy `messages.en.xlf` and `reporting.en.xlf` to `.<locale>.xlf`,
translate the `<target>` elements and run `bin/console kimai:reload -n`.

## Contributing

Bug reports, translations and pull requests are welcome. See
[CONTRIBUTING.md](CONTRIBUTING.md).

## License

MIT. See [LICENSE](LICENSE).
