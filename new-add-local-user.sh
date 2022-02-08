#!/bin/bash
#Не работает, трабы с созданием пасса при помощи генератора
if [[ "${UID}" -ne 0 ]]
then 
	echo "Запустите с правами супер-пользователя"
	exit 1
fi
if [[ "${#}" -lt 1 ]]
then
	echo "Usage: ${0} USER_NAME [COMMENT]"
	exit 1
fi

USER_NAME="${1}"

shift 
COMMENT="${@}"

#Generate a Password
PASSWORD=$(date +%s%N | sha256sum | head -c48)

#Create the user with pass
useradd -c "$COMMENT" -m ${USER_NAME} 

if [[ "${?}" -ne 0 ]]
then
	echo "Аккаунт не был создан"
	exit 1
fi
#echo ${PASSWORD}:${PASSWORD} | passwd $USER_NAME
echo "${USER_NAME}""${PASSWORD}" | chpasswd
echo "${PASSWORD}"
passwd -e ${USER_NAME}

echo "${USER_NAME}"
