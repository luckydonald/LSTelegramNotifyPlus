# MIT Licensed file

BUILD_DIR=${PWD}/build/artifacts/
ZIP_NAME="LSTelegramNotifyPlus"

function calculate_zip_name() {
  version=$(awk -F'[<>]' '/<version>/{print $3; exit}' config.xml)
  echo "${ZIP_NAME}_v${version}.zip"
}

function clean() {
    rm -rf vendor/
    rm "$(calculate_zip_name)"
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
      -r config.xml \
    ;
}
