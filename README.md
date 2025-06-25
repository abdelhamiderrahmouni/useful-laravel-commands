<p align="center">
    <p align="center">
        <a href="https://github.com/abdelhamiderrahmouni/useful-laravel-commands/actions"><img alt="GitHub Workflow Status (main)" src="https://img.shields.io/github/actions/workflow/status/abdelhamiderrahmouni/useful-laravel-commands/tests.yml?branch=main&label=tests&style=round-square"></a>
        <a href="https://packagist.org/packages/abdelhamiderrahmouni/useful-laravel-commands"><img alt="Total Downloads" src="https://img.shields.io/packagist/dt/abdelhamiderrahmouni/useful-laravel-commands"></a>
        <a href="https://packagist.org/packages/abdelhamiderrahmouni/useful-laravel-commands"><img alt="Latest Version" src="https://img.shields.io/packagist/v/abdelhamiderrahmouni/useful-laravel-commands"></a>
        <a href="https://packagist.org/packages/abdelhamiderrahmouni/useful-laravel-commands"><img alt="License" src="https://img.shields.io/github/license/abdelhamiderrahmouni/useful-laravel-commands"></a>
    </p>
</p>

------
# Useful Laravel Commands
A collection of useful Laravel commands to enhance your development workflow.

## Usage
### Password reset
To reset the password for a user, you can use the following command:

```bash
php artisan reset-password {email}
```
Replace `{email}` with the email address of the user whose password you want to reset.
The default password will be set to `password`.

To change the default password, you can use the command as follows:

```bash
php artisan reset-password {email} {new_password}
```

if you want to change the login used to find the user, you can use the command as follows:
The default login is email
```bash
php artisan reset-password {login} {new_password} --login={column_name} 
```


## Contributing

Thank you for considering contributing to `Useful Laravel Commands`! The contribution guide can be found in the [CONTRIBUTING.md](CONTRIBUTING.md) file.

---
`Useful Laravel Commands` is an open-sourced software licensed under the **[MIT license](https://opensource.org/licenses/MIT)**.


