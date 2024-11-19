# MadelineProto Self UserBot

## Usage

- Clone the repo:

    ```shell
    git clone https://github.com/Piagrammist/mp-self && cd mp-self
    ```

- Install the dependencies:

    ```shell
    composer install
    ```

- Rename the [`config.example.php`](config.example.php) file to `config.php`, and place your credentials there.

- Execute the entry point:

    ```shell
    php index.php
    ```

## Robot Commands

`.bot <on|off>`
_Make the robot active or inactive._

`.quiet <on|off>`
_Switch the robot's quiet mode._

`.delay <num_x>`
_Change the periodic actions' delay._

`.ping`
_Check robot's responding._

`.x <code>`
_Execute the php code._

`.cp <?peer> (reply)`
_Copy and send the replied message to any chat. (default peer: current chat)_

`.info <?peer> [reply]`
_Get info about the chat (+ a user depending on the reply/arg value)._

`.status`
_Get info about the server & robot's chats._

``.style <*|_|__|`|~|none>``
_Switch the text styling._

`.spell <txt>`
_Split text into letters and send them separately._

`.spam <num_x> <?num_y> <txt>`
_Send x messages, each containing y * txt. (y can be omitted!)_

`.del <num_x>`
_Delete x messages from the chat. (0 < x < 100)_

> [!NOTE]
>
> - _Supported command prefixes are `/` `.` `!`_
>
> - _`()` means required reply, and `[]`, an optional one._
