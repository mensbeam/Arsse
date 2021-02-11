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

Setting a user's password is nearly identical to adding a user:

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

## Managing login tokens for Miniflux

[Miniflux](/en/Supported_Protocols/Miniflux) clients may optionally log in using tokens: randomly-generated strings which act as persistent passwords. For now these must be generated using the command-line interface:

```console
$ sudo -u www-data php arsse.php token create "jane.doe"
xRK0huUE9KHNHf_x_H8JG0oRDo4t_WV44whBtr8Ckf0=
```

Multiple tokens may be generated for use with different clients, and descriptive labels can be assigned for later identification:

```console
$ sudo -u www-data php arsse.php token create "jane.doe" Newsflash
xRK0huUE9KHNHf_x_H8JG0oRDo4t_WV44whBtr8Ckf0=
$ sudo -u www-data php arsse.php token create "jane.doe" Reminiflux
L7asI2X_d-krinGJd1GsiRdFm2o06ZUlgD22H913hK4=
```

There are also commands for listing and revoking tokens. Please consult the integrated help for more details.

# Setting and changing user metadata

Users may also have various metadata properties set. These largely exist for compatibility with [the Miniflux protocol](/en/Supported_Protocols/Miniflux) and have no significant effect. One exception to this, however, is the `admin` flag, which signals whether the user may perform privileged operations where they exist in the supported protocols.

The flag may be changed using the following command:

```sh
sudo -u www-data php arsse.php user set "jane.doe" admin true
```

As a shortcut it is also possible to create administrators directly:

```sh
sudo -u www-data php arsse.php user add "user@example.com" "example password" --admin
```

Please consult the integrated help for more details on metadata and their effects.
