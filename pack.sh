# MIT Licensed file

BUILD_DIR=${PWD}/build/artifacts/
ZIP_NAME="LSTelegramNotifyPlus"

function calculate_zip_name() {
  tag=$(git describe --tags --abbrev=0)
  echo "${ZIP_NAME}_${tag}.zip"
}

function clean() {
    rm -rf vendor/
    rm "${ZIP_NAME}_*.zip"
}

function composer() {
    docker run --rm --name composer_runner --interactive --tty \
      --volume $PWD:/app \
      --volume ./.composer:/tmp \
      --user $(id -u):$(id -g) \
      composer "${@}"
}

function pack() {
    clean;
    composer install --no-dev;
    zip $(calculate_zip_name) \
      -r "LSTelegramNotifyPlus.php" \
      -r vendor/ \
    ;
}
