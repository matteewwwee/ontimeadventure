import esbuild from 'esbuild';
import { sassPlugin } from 'esbuild-sass-plugin';
import postcss from 'postcss';
import autoprefixer from 'autoprefixer';
import fs from 'fs-extra';
import path from 'path';

async function copyDependencies() {
  const packageJson = await fs.readJson('./package.json');
  const dependencies = Object.keys(packageJson.dependencies);

  await Promise.all(
    dependencies.map(async (dependency) => {
      const dependencyPath = path.join('./node_modules', dependency);
      const distPath = path.join(dependencyPath, 'dist');
      const libPath = path.join('./dist/assets/libs', dependency);

      if (await fs.pathExists(distPath)) {
        await fs.copy(distPath, libPath);
      } else {
        await fs.copy(dependencyPath, libPath);
      }
    })
  );
}

async function copyAssets() {
  const srcAssetsDir = 'src/assets';
  const distAssetsDir = 'dist/assets';

  await fs.ensureDir(distAssetsDir);

  const shouldExcludeDirectory = (dirName) => {
    return dirName === 'css' || dirName === 'scss' || dirName === 'js';
  };

  async function copyFilesAndDirs(src, dest) {
    const items = await fs.readdir(src);

    for (const item of items) {
      const srcItemPath = path.join(src, item);
      const destItemPath = path.join(dest, item);

      const stats = await fs.stat(srcItemPath);

      if (stats.isDirectory()) {
        if (!shouldExcludeDirectory(item)) {
          await fs.ensureDir(destItemPath);
          await copyFilesAndDirs(srcItemPath, destItemPath);
        }
      } else {
        await fs.copy(srcItemPath, destItemPath);
      }
    }
  }
  await copyFilesAndDirs(srcAssetsDir, distAssetsDir);
  console.log('⚡ Assets Compiled! ⚡');
}

async function cleanDistFolder() {
  try {
    await fs.emptyDir('dist');
    console.log('dist folder cleaned.');
  } catch (error) {
    console.error('Error cleaning dist folder:', error);
  }
}

cleanDistFolder().then(async () => {
  try {
    await esbuild.build({
      entryPoints: [
        'src/assets/scss/styles.scss',
        'src/assets/scss/icons.scss'
      ],
      outdir: 'dist/assets/css',
      bundle: true,
      plugins: [
        sassPlugin({
          async transform(source) {
            const { css } = await postcss([autoprefixer]).process(source, { from: undefined });
            return css;
          },
        }),
      ],
      loader: {
        ".png": "file", ".jpg": "file", ".jpeg": "file", ".svg": "file", ".gif": "file",
        ".woff": "file", ".ttf": "file", ".eot": "file", ".woff2": "file"
      }
    });
    console.log('⚡ SCSS Compiled! ⚡');

    await esbuild.build({
      entryPoints: ['src/assets/js/main.js', 'src/assets/js/custom.js', 'src/assets/js/sticky.js', 'src/assets/js/defaultmenu.js'],
      outdir: 'dist/assets/js',
      bundle: false,
    }).catch(e => {
        // Fallback: Just copy js files if esbuild bundle=false fails
    });
    
    // Copy all js directly
    await fs.copy('src/assets/js', 'dist/assets/js');

    await copyDependencies();
    await copyAssets();
    
    console.log('✅ ALL BUILD PROCESSES COMPLETED!');
  } catch(e) {
    console.error(e);
    process.exit(1);
  }
});
