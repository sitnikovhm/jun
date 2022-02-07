#!/bin/bash
USER='root'
USERID=$(id -un)
if [[ "$USERID" != "$USER" ]]
then
	echo 'exit 1'
	exit 1
fi
read -p 'Введи имя пользователя: ' USERNAME
read -p 'Введите настоящее имя: ' IRLNAME
read -p 'Введите пароль: ' PASSWORD

if [[ -z "$PASSWORD"  ]] 
then
        echo 'Пароль не может быть пустым'
	exit 
fi

useradd -c "$IRLNAME" -m "$USERNAME" -p "$PASSWORD" 

echo "Login:$USERNAME"
echo "Password:$PASSWORD"
echo "Bio:$IRLNAME"
echo "Hostname:$HOSTNAME"
