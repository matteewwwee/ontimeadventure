import esbuild from 'esbuild';
import { sassPlugin } from 'esbuild-sass-plugin';
import postcss from 'postcss';
import autoprefixer from 'autoprefixer';
import fs from 'fs-extra';
import path from 'path';
import browserSync from "browser-sync"
import chokidar from "chokidar"
const bs = browserSync.create()

async function buildHTMLWithPartials() {
  const srcDir = 'src/html';
  const outDir = 'dist/html';
  const htmlFiles = await fs.readdir(srcDir);
  for (const htmlFile of htmlFiles) {
    if (path.extname(htmlFile) !== '.html') {
      continue;
    }
    const htmlFilePath = path.join(srcDir, htmlFile);
    const outputFilePath = path.join(outDir, htmlFile);
    let htmlContent = await fs.readFile(htmlFilePath, 'utf-8');
    const includePattern = /<!--\s*include\s+(.*?)\s*-->/g;
    let match;
    let allFilesInPartials = await fs.readdir('src/html/partials')
    while ((match = includePattern.exec(htmlContent))) {
      const includeComment = match[0];
      const partialPath = match[1];
      try {
        if(allFilesInPartials.includes(partialPath.replace(/"+/g, ''))){
          const partialContent = await fs.readFile(path.join(srcDir, 'partials', partialPath.replace(/"+/g, '')), 'utf-8');
          htmlContent = htmlContent.replace(includeComment, partialContent);
        }
      } catch (error) {
        console.error(`Failed to include partial '${partialPath}': ${error.message}`);
      }
    }
    await fs.ensureDir(outDir);
    await fs.writeFile(outputFilePath, htmlContent);
  }
  console.log(`⚡ Processed HTML files ⚡`);
}

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
    return dirName === 'css' || dirName === 'scss';
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
        await copyFileUsingStreams(srcItemPath, destItemPath);
      }
    }
  }
  async function copyFileUsingStreams(src, dest) {
    const readStream = fs.createReadStream(src);
    const writeStream = fs.createWriteStream(dest);

    return new Promise((resolve, reject) => {
      readStream.on('error', reject);
      writeStream.on('error', reject);
      writeStream.on('finish', resolve);

      readStream.pipe(writeStream);
    });
  }
  await copyFilesAndDirs(srcAssetsDir, distAssetsDir);
  console.log('⚡ Assets Compiled! ⚡');
}

async function replaceIncludeTags(htmlContent, srcDir) {
  const includePattern = /<!--\s*include\s+(.*?)\s*-->/g;
  const matches = [...htmlContent.matchAll(includePattern)];

  for (const match of matches) {
    const partialPath = match[1].replace(/"+/g, '');
    const partialFilePath = path.join(srcDir, 'partials', partialPath);
    const partialContent = await fs.readFile(partialFilePath, 'utf-8');
    htmlContent = htmlContent.replace(match[0], partialContent);
  }

  return htmlContent;
}

async function processHTMLFiles(srcFile, distDir) {
  if (!srcFile.includes('partials')) {
    const srcFilePath = path.join(srcFile);
    const distFilePath = path.join(distDir, path.basename(srcFile));
    let htmlContent = await fs.readFile(srcFilePath, 'utf-8');
    htmlContent = await replaceIncludeTags(htmlContent, path.dirname(srcFilePath));
    await fs.writeFile(distFilePath, htmlContent);
    console.log(`⚡ Updated ${srcFile} in ${distFilePath}`);
  }
}

async function cleanDistFolder() {
  try {
    await fs.emptyDir('dist');
    console.log('dist folder cleaned.');
  } catch (error) {
    console.error('Error cleaning dist folder:', error);
  }
}

cleanDistFolder().then(() => {
  const ctx = esbuild.build({
    logLevel: 'debug',
    metafile: true,
    entryPoints: [
      'src/assets/scss/styles.scss',
      'src/assets/scss/icons.scss'
    ],
    outdir: 'dist/assets/css',
    bundle: true,
    // watch: true,
    plugins: [
      sassPlugin({
        async transform(source) {
          const { css } = await postcss([autoprefixer]).process(source, { from: undefined });
          return css;
        },
      }),
    ],
    loader: {
      ".png": "file",
      ".jpg": "file",
      ".jpeg": "file",
      ".svg": "file",
      ".gif": "file",
      ".woff": "file",
      ".ttf": "file",
      ".eot": "file",
      ".woff2": "file",
      ".html": "file"
    }
  })
  ctx.then(async () => {
    console.log('⚡ Styles & Scripts Compiled! ⚡ ');
    // To Libs Dependencies
    await copyDependencies().then(() => {
      console.log('⚡ libs Compiled! ⚡ ');
    });
    // To HTML Partials
    await buildHTMLWithPartials()
    // To Copy the Assets
    await copyAssets()
  
    bs.init({
      server: {
        baseDir: 'dist',
        // index: 'html/index.html',
        // directory: true,
      },
      startPath: 'html/index.html',
      open: true,
      watch: true,
      files: ['dist/**/*'],
      online: false,
      tunnel: true,
      logLevel: 'info',
    });
  
    bs.watch('dist/**/*').on('change', bs.reload)
    const srcHtmlDir = 'src/html';
    const distHtmlDir = 'dist/html';
    const srcscssDir = 'src/assets/scss';
    const distscssDir = 'dist/assets/css';
    const srcjsDir = 'src/assets/js';
    const distjsDir = 'dist/assets/js';
  
    function watchAndProcessHTMLFiles() {
      const watcher = chokidar.watch(srcHtmlDir, {
        ignoreInitial: true,
      });
      watcher.on('change', async (srcFile) => {
        await processHTMLFiles(srcFile, distHtmlDir);
      });
      console.log(`⚡ Watching HTML files in ${srcHtmlDir} for changes...`);
    }
  
    function watchAndscssFiles() {
      const watcher = chokidar.watch(srcscssDir, {
        ignoreInitial: true,
      });
      watcher.on('change', async (srcscssDir) => {
        await buildCSS(srcscssDir, distscssDir);
      });
      console.log(`⚡ Watching Scss files in ${srcscssDir} for changes...`);
    }
    function watchAndjsFiles() {
      const watcher = chokidar.watch(srcjsDir, {
        ignoreInitial: true,
      });
      watcher.on('change', async (srcjsDir) => {
        await buildJS(srcjsDir, distjsDir);
      });
      console.log(`⚡ Watching js files in ${srcjsDir} for changes...`);
    }
    // To Change the HTML
    watchAndProcessHTMLFiles();
    watchAndscssFiles();
    watchAndjsFiles();
  
  })
  ctx.catch(() => process.exit(1));
});

async function buildCSS() {
  const ctx = esbuild.build({
    logLevel: 'debug',
    metafile: true,
    entryPoints: [
      'src/assets/scss/styles.scss',
      'src/assets/scss/icons.scss'
    ],
    outdir: 'dist/assets/css',
    bundle: true,
    // watch: true,
    plugins: [
      sassPlugin({
        async transform(source) {
          const { css } = await postcss([autoprefixer]).process(source, { from: undefined });
          return css;
        },
      }),
    ],
    loader: {
      ".png": "file",
      ".jpg": "file",
      ".jpeg": "file",
      ".svg": "file",
      ".gif": "file",
      ".woff": "file",
      ".ttf": "file",
      ".eot": "file",
      ".woff2": "file",
      ".html": "file"
    }
  });

  await ctx;

  console.log('⚡ Styles Compiled and copied to dist/css! ⚡');
}
async function buildJS() {
  const ctx = esbuild.build({
    logLevel: 'debug',
    metafile: true,
    entryPoints: ['src/assets/js/*.js',],
    outdir: 'dist/assets/js',
    bundle: true,
  });

  await ctx;

  console.log('⚡ Styles Compiled and copied to dist/js! ⚡');
}

chokidar.watch('src/assets/scss/**/*.scss').on('change', async () => {
  console.log(`File changed: ${filePath}`);
  try {
    await buildCSS();
  } catch (error) {
    console.error('Error while rebuilding SCSS:', error);
  }
})

async function copyJS(jsFile) {
  const jsFileName = path.basename(jsFile);
  const distFilePath = path.join('dist/assets/js', jsFileName);
  await fs.copy(jsFile, distFilePath);
  console.log(`⚡ Copied ${jsFileName} to dist/assets/js! ⚡`);
}

chokidar.watch('src/assets/js/*.js').on('change', async (jsFile) => { 
  console.log(`JS File changed: ${jsFile}`);
  try {
    await copyJS(jsFile);
  } catch (error) {
    console.error('Error while copying JS:', error);
  }
});

// chokidar.watch('src/assets/js/*.js', { ignoreInitial: true })
//   .on('add', async (jsFile) => {
//     console.log(`JS File added: ${jsFile}`);
//     try {
//       await copyJS(jsFile);
//     } catch (error) {
//       console.error('Error while copying JS:', error);
//     }
//   })
//   .on('change', async (jsFile) => {
//     console.log(`JS File changed: ${jsFile}`);
//     try {
//       await copyJS(jsFile);
//     } catch (error) {
//       console.error('Error while copying JS:', error);
//     }
//   })
//   .on('unlink', async (jsFile) => {
//     const jsFileName = path.basename(jsFile);
//     const distFilePath = path.join('dist/assets/js', jsFileName);
//     try {
//       await fs.remove(distFilePath);
//       console.log(`⚡ Deleted ${jsFileName} from dist/assets/js! ⚡`);
//     } catch (error) {
//       console.error('Error while deleting JS:', error);
//     }
//   });

// For the Assets added and delete

async function copyChangedFile(changedFile) {
  const srcAssetsDir = 'src/assets';
  const distAssetsDir = 'dist/assets';

  // Ensure the destination directory exists
  await fs.ensureDir(distAssetsDir);

  // Calculate the destination path for the changed file
  const relativePath = path.relative(srcAssetsDir, changedFile);
  const destFile = path.join(distAssetsDir, relativePath);

  // Copy the changed file to the destination
  await fs.copyFile(changedFile, destFile);
  console.log('⚡ File copied to dist: ', destFile);
}

async function deleteFile(deletedFile) {
  const distAssetsDir = 'dist/assets';
  const relativePath = path.relative('src/assets', deletedFile);
  const destFile = path.join(distAssetsDir, relativePath);

  try {
    await fs.remove(destFile);
    console.log('⚡ File deleted from dist: ', destFile);
  } catch (error) {
    console.error('Error while deleting file:', error);
  }
}

chokidar.watch('src/assets/**', { ignoreInitial: true })
  .on('add', async (addedFile) => {
    try {
      console.log("File added: ", addedFile);
      if (!addedFile.includes(".scss")) {
        await copyChangedFile(addedFile);
      }
    } catch (error) {
      console.error('Error while copying Assets:', error);
    }
  })
  .on('unlink', async (deletedFile) => {
    try {
      console.log("File deleted: ", deletedFile);
      if (!deletedFile.includes(".scss")) {
        await deleteFile(deletedFile);
      }
    } catch (error) {
      console.error('Error while deleting file:', error);
    }
  })
  .on('change', async (changedFile) => {
    try {
      console.log("File changed: ", changedFile);
      if (!changedFile.includes(".scss")) {
        await copyChangedFile(changedFile);
      }
    } catch (error) {
      console.error('Error while copying Assets:', error);
    }
  });
// For the Html Add and delete 

async function htmlcCopyChangedFile(changedFile) {
  const srcAssetsDir = 'src/html';
  const distAssetsDir = 'dist/html';

  // Ensure the destination directory exists
  await fs.ensureDir(distAssetsDir);

  // Calculate the destination path for the changed file
  const relativePath = path.relative(srcAssetsDir, changedFile);
  const destFile = path.join(distAssetsDir, relativePath);

  // Copy the changed file to the destination
  await fs.copyFile(changedFile, destFile);
  console.log('⚡ File copied to dist: ', destFile);
}

async function htmlDeleteFile(deletedFile) {
  const distAssetsDir = 'dist/html';
  const relativePath = path.relative('src/html', deletedFile);
  const destFile = path.join(distAssetsDir, relativePath);

  try {
    await fs.remove(destFile);
    console.log('⚡ File deleted from dist: ', destFile);
  } catch (error) {
    console.error('Error while deleting file:', error);
  }
}

chokidar.watch('src/html/**', { ignoreInitial: true })
  .on('add', async (addedFile) => {
    try {
      console.log("File added: ", addedFile);
      if (!addedFile.includes("partials")) {
        await htmlcCopyChangedFile(addedFile);
      }
    } catch (error) {
      console.error('Error while copying Html:', error);
    }
  })
  .on('unlink', async (deletedFile) => {
    try {
      console.log("File deleted: ", deletedFile);
      if (!deletedFile.includes("partials")) {
        await htmlDeleteFile(deletedFile);
      }
    } catch (error) {
      console.error('Error while deleting file:', error);
    }
  })

async function updateHTMLFilesWithPartialChange(changedPartialPath) {
    const srcDir = 'src/html';
    const outDir = 'dist/html';
    const htmlFiles = await fs.readdir(srcDir);
  
    try {
      const changedPartialContent = await fs.readFile(changedPartialPath, 'utf-8');
  
      for (const htmlFile of htmlFiles) {
        if (path.extname(htmlFile) !== '.html') {
          continue;
        }
  
        const htmlFilePath = path.join(srcDir, htmlFile);
        const outputFilePath = path.join(outDir, htmlFile);
        let htmlContent = await fs.readFile(htmlFilePath, 'utf-8');
        const includePattern = /<!--\s*include\s+(.*?)\s*-->/g;
        let match;
        
        while ((match = includePattern.exec(htmlContent))) {
          const includeComment = match[0];
          const partialPath = match[1];
  
          try {
            if (partialPath.replace(/"+/g, '') === path.basename(changedPartialPath)) {
              htmlContent = htmlContent.replace(includeComment, changedPartialContent);
            } else {
              const partialContent = await fs.readFile(path.join(srcDir, 'partials', partialPath.replace(/"+/g, '')), 'utf-8');
              htmlContent = htmlContent.replace(includeComment, partialContent);
            }
          } catch (error) {
            console.error(`Failed to include partial '${partialPath}': ${error.message}`);
          }
        }
  
        await fs.ensureDir(outDir);
        await fs.writeFile(outputFilePath, htmlContent);
        // console.log(`⚡ Updated ${htmlFile} in ${outputFilePath}`);
      }
  
      console.log(`⚡ Processed HTML files ⚡`);
    } catch (error) {
      console.error(`Failed to read changed partial file: ${error.message}`);
    }
}

function debounce(func, delay) {
  let timeoutId;
  return function (...args) {
    clearTimeout(timeoutId);
    timeoutId = setTimeout(() => {
      func.apply(this, args);
    }, delay);
  };
}

// Usage
const debouncedUpdateHTML = debounce(updateHTMLFilesWithPartialChange, 5000);

function partialsChange() {
  const watcher = chokidar.watch('src/html/partials', {
    ignoreInitial: true,
  });
  watcher.on('change', async (srcFile) => {
    debouncedUpdateHTML(srcFile);
  });
}

partialsChange();