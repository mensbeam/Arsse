module.exports = ctx => ({
  //map: ctx.options.map,
  parser: 'postcss-scss',
  //syntax: 'postcss-scss',
  plugins: {
    'postcss-import': { root: ctx.file.dirname },
    'postcss-discard-comments': {},
    'postcss-sassy-mixins': {},
    'postcss-custom-media': {preserve: false},
    'postcss-media-minmax': {},
    'postcss-custom-properties': {preserve: false},
    'postcss-color-function': {},
    'postcss-nested': {},
    'autoprefixer': {},
    'postcss-csso': {},
  }
})
