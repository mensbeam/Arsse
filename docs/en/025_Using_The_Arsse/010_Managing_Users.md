[TOC]

# Preface

This section describes in brief some CLI commands. Please read [the general notes on the command line interface](index) before continuing.

# Adding users

When first installed, The Arsse has no users configured. You may add users by executing the following command:

```sh
sudo -u www-data php arsse.php user add "user@example.com" "example password"
```

The password argument is optional: if no password is provided, a random one is generated and printed out:

```console
$ sudo -u www-data php arsse.php user add "jane.doe"
Ji0ivMYqi6gKxQK1MHuE
```

# Setting and changing passwords

Setting's a user's password is practically identical to adding a password:

```sh
sudo -u www-data php arsse.php user set-pass "user@example.com" "new password"
```

As when adding a user, the password argument is optional: if no password is provided, a random one is generated and printed out:

```console
$ sudo -u www-data php arsse.php user set-pass "jane.doe"
Ummn173XjbJT4J3Gnx0a
```

## Setting and changing passwords for Fever

Before a user can make use of [the Fever protocol](/en/Supported_Protocols/Fever), a Fever-specific password for that user must be set. It is _highly recommended_ that this not be the samer as the user's main password. The password can be set by adding the `--fever` option to the normal password-changing command:

```sh
sudo -u www-data php arsse.php user set-pass --fever "user@example.com" "fever password"
```

As when setting a main password, the password argument is optional: if no password is provided, a random one is generated and printed out:

```console
$ sudo -u www-data php arsse.php user set-pass --fever "jane.doe"
YfZJHq4fNTRUKDYhzQdR
```



        

