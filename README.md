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

`.del <num_x> <?s|service> <?a|after> [reply]`
_Delete x messages from the chat._

> - _If `service` is set, only service messages will be deleted._
>
> - _If replied to a message, only messages before (after, if set) the replied message will be deleted._

`.backup <?c|clear>`
_Make a profile backup, or delete the previous one._

`.restore`
_Restore the profile backup if it exists._

`.clone <?peer> [reply]`
_Clone profile of the peer, replied user or current chat._

`{} (reply)`
_Get JSON view of the replied message update._

---

> [!NOTE]
>
> - _Supported command prefixes are `/` `.` `!`_
>
> - _`()` means required reply, and `[]`, an optional one._
