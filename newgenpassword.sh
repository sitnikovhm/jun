#!/bin/bash
#echo "You used $(dirname ${0}) as the path to the $(basename ${0}) script."
NUMBER="${#}"
echo "You ${NUMBER} arguments."
#if [[ "$NUMBER"" -lt 1 ]]
#then
#	echo "Usage ${0} USER NAME."
#	exit 1
#fi
for USER_NAME in "${@}"
do
	PASSWORD=$(date +%s%N | sha256sum | head -c48)
	echo "${USER_NAME}: ${PASSWORD}"
done
