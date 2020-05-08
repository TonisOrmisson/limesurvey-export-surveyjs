# THIS IS WORK IN PROGRESS!
...


# Installation & set-up
## Install to plugins folder

```
cd /LimeSurveyFolder/plugins
```


```
git clone https://github.com/TonisOrmisson/limesurvey-export-surveyjs ExportSurveyJs
```

```
cd ExportSurveyJs && composer install
```

##
Activate plugin from Plugin manager

##
Find the plugin from survey tools menu.

# Updating

go to plugin folder
```
cd /LimeSurveyFolder/plugins/ExportSurveyJs
```

Get updates via git.
`git pull` or `git fetch --all && git checkout my-version-tag`


Run install command to make sure dependencies are updated if necessary.
```
composer install --no-dev
```
