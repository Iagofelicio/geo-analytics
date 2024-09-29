## Geo Analytics

> **Geo Analytics** is a Statamic addon that empowers you to understand your **website's traffic** on a geographic level.
>
> It analyzes all website requests and uses **geographic information** providers to deliver a comprehensive set of traffic insights. Gain valuable knowledge about your audience's geographic location through a user-friendly utility dashboard.
>
> The dashboard displays all data alongside insights for the current day, the last 7 days, and the last 30 days, **allowing you to track trends and identify patterns over time**.

## Features

**This addon does:**

- A summary with total number of requests, top city and country, top URI and top IP address.
- A visual representation of the number of requests from different countries, using color intensity to indicate the volume of traffic.
- A chart that visualizes daily request over time, categorized by HTTP status code.
- A detailed overview of website traffic at the URI level, highlighting popular resources, geographic reach, and overall performance. It helps identify high-traffic URIs, common errors, and opportunities for optimization.
- A breakdown of website traffic by city and country, highlighting the volume of requests, successful requests, and overall success rate for each location. It helps in identifying top performing cities and countries, as well as potential issues based on geographic location.

**In addition, it allows the user to:**

- Download all data to extend the analysis.
- Manage application cache.
- Choose between Geo IP Providers options (currently, 3 options are offered).
- Pause tracking of website requests and restore when necessary without removing the addon.
- Block and enable tracking of specific IPs
- Include or hide requests with different HTTP Status codes

## How to Install

#### Dependencies

[Laravel Numbers Helpers](https://laravel.com/docs/11.x/helpers#numbers) is used in the dashboard and requires the installation of the **PHP intl** extension. Its installation is performed in the same way as any PHP extension required by Laravel.

For example, on Ubuntu servers the installation looks like:

```bash
sudo apt install php-intl
```

or:

```bash
sudo apt install php8.x-intl
```

#### Installation

You can search for this addon in the `Tools > Addons` section of the Statamic control panel and click **install**, or run the following command from your project root:

```bash
composer require iagofelicio/geo-analytics
```

#### Cron

Geo Analytics uses periodically scheduled commands to update the data displayed on the dashboard using Laravel's Scheduler. One command runs every 15 seconds to include new requests and another command runs at midnight to update information for the daily, 7-day and 30-day periods.

When using Laravel's scheduler, we only need to add a single cron configuration entry to our server that runs the schedule:run command every minute.

```
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

## How to Use

Once installed, the addon will start monitoring requests made to the website and storing the necessary information.

Simply access `Utilities > Geo Analytics` to view the dashboard.

At the bottom of the dashboard, you can change your **user preferences**.

The default Geo IP Provider to be used is [ip-api.com](https://ip-api.com/), since it does not require a token despite making http requests (without SSL) in its free mode and with a limit of 45 requests per minute. To change to [apiip.net](https://apiip.net/) or [ip2location.io](https://www.ip2location.io/), you must include the user token. Check the documentation for each one to understand the advantages and disadvantages.

Geo Analytics is adapted for the free version of ip-api.com. If the site receives more than 45 requests in a one-minute interval, the process responsible for updating the dashboard data will wait for the remaining time to use the free quota.

Ideally, you would use a paid version of some of the Geo IP Providers suggested, but the free version is fine if you do not have a massive amount of data per minute.

Be aware that requests are stored at the time they occur, but do not trigger processing so as not to affect page load time. The background commands are responsible for handling new requests.

It is strongly recommended to **keep IP Cache enabled**. Geographic details of IPs that have already accessed the website once will be pre-stored to avoid consuming any quotas from Geo IP Providers.

## In-depth details

### About external URLs

All of the following service providers can or will be used in the application. Please be aware in case firewall restrictions need to be addressed.

- Default Geo IP Provider: [http://ip-api.com](http://ip-api.com)
- Optional Geo IP Provider: [https://apiip.net](https://apiip.net)
- Optional Geo IP Provider: [https://api.ip2location.io](https://api.ip2location.io)
- Get your public IP (useful when developing in localhost): [https://api.myip.com](https://api.myip.com)

### About log files

All log files will be stored as JSON, GEOJSON and/or YAML files in the storage folder. The idea behind this plugin is to avoid the need to configure databases, similar to what Statamic does.

Under no circumstances should you edit the files manually. All user settings and preferences should be edited on the Utility page.

### About the map

The country data used in the requests map is obtained from [Natural Earth](https://www.naturalearthdata.com/).

Natural Earth is a public domain map dataset available at 1:10m, 1:50m, and 1:110 million scales. Featuring tightly integrated vector and raster data, with Natural Earth you can make a variety of visually pleasing, well-crafted maps with cartography or GIS software.

It is unclear where each provider sources the geographic data. This may result in different country names being used or requests being sent to sources that do not exist in the country layer projected onto the map.

If you encounter any issues related to country boundaries, definitions, or terms, please know that there is not much we can do. But please contact us and we may be able to find a solution for your specific problem.

## Contributions

Do you have any ideas on how to improve this add-on? Create an issue on Github and I'll try to analyze all cases and suggestions as soon as possible.
