# Streetcred-Bot

A Telegram bot you can add to group chats. Chat participants may reward each other with streetcred/respect/karma which will be logged and kept track of by the bot.

# Usage

If a user wants to give streetcred to a recipient, he should reply to the recipient with the command `respect` which will add +1 streetcred to the recipient.
The user may also add a specific amount of streetcred by noting the amount after the command: `respect 10` will add +10 streetcred to the recipient.
Streetcred can also be removed by using a negative number: `respect -5`

The user can know his current streetcred by using the `/myrespect` command.

Streetcred is tracked individually for each chat.

# Live Demo

Add @streetcred_bot in Telegram to your group.

# How it works

As I do not have access to Database services, the bot currently uses local blobstorage (aka textfiles) to save all the required information. Each individual group chat will correspond to its own file, with all the user's streetcred values saved inside.
It advised to host the bot on a secret URL so people do not have easy access to the stored data. Telegram will be the only party who knows about the bot URL.