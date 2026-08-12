# Laravel 11 setup

Create the runtime application in this directory with `laravel new`, then copy the `app`, `database`, and `routes` files into the generated Laravel 11 project. The scaffold intentionally keeps the frontend independent: configure `NEXT_PUBLIC_DOMAIN_API_URL` and proxy the API when ready.

Run the queue worker with `php artisan queue:work` and the scheduler with `php artisan schedule:work`.
