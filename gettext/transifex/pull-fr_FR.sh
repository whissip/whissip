echo Getting updates from Transifex
# tx pull -a
tx pull -l fr_FR

echo "Copy fr_FR (transifex) to fr_FR locale (b2evo)"
cp translations/b2evolution.b2evomessages/fr_FR.po ../../blogs/locales/fr_FR/LC_MESSAGES/messages.po
