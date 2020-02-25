# ESO Raidplanner ![CI](https://github.com/Woeler/eso-raidplanner/workflows/CI/badge.svg?branch=master)

ESO Raidplanner uses [DDEV](https://github.com/drud/ddev) as a development environment. You can get it running with a vanilla LAMP stack, but we strongly recommend you use [DDEV](https://github.com/drud/ddev).

ESO Raidplanner is based on [Symfony 4.4](https://symfony.com/releases/4.4).

## Development setup

* Clone this repository, or your fork.
* Run `ddev start`.
* Run `ddev composer install`.
* Set the following variables in your `.env.local` file: `OAUTH_DISCORD_CLIENT_ID`, `OAUTH_DISCORD_CLIENT_SECRET`, `DISCORD_BOT_TOKEN`, `DISCORD_BOT_CLIENT_ID` and `BOT_AUTH_TOKEN`.
* Run `ddev exec bin/console doctrine:migrations:migrate`.
* Run `ddev exec yarn install`.
* Run `ddev exec yarn encore dev`.
* Your application development environment is now set up.

### Development setup Discord bot

* Move into the `bot/` folder.
* Copy `config.example.json` to `config.json`.
* Set your Discord bot token in `config.json` in the field `botToken`.
* Set your auth token in `config.json` in the field `authToken`. Make sure that the `authToken` is the same as your `BOT_AUTH_TOKEN` .env variable.
* Run `npm install`.
* Start the both with `node bot.js`.
