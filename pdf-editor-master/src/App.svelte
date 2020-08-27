<script>
  import { onMount } from "svelte";
  import { fly } from "svelte/transition";
  import Tailwind from "./Tailwind.svelte";
  import PDFPage from "./PDFPage.svelte";
  import Image from "./Image.svelte";
  import Text from "./Text.svelte";
  import Drawing from "./Drawing.svelte";
  import DrawingCanvas from "./DrawingCanvas.svelte";
  import { fetchFont } from "./utils/prepareAssets.js";
  import {
    readAsArrayBuffer,
    readAsImage,
    readAsPDF,
    readAsDataURL
  } from "./utils/asyncReader.js";
  import { ggID } from "./utils/helper.js";
  import { save } from "./utils/PDF.js";
  const genID = ggID();
  let pdfFile;
  let pdfName = "";
  let pages = [];
  let pagesScale = [];
  let allObjects = [];
  let currentFont = "Times-Roman";
  let focusId = null;
  let selectedPageIndex = -1;
  let saving = false;
  let addingDrawing = false;
  let pdfZoomValue = 1;
  // for test purpose
  /*onMount(async () => {
    try {
      const res = await fetch("/test.pdf");
      const pdfBlob = await res.blob();
      await addPDF(pdfBlob);
      selectedPageIndex = 0;
      fetchFont(currentFont);
      // const imgBlob = await (await fetch("/test.jpg")).blob();
      // addImage(imgBlob);
      // addTextField("測試!");
      // addDrawing(200, 100, "M30,30 L100,50 L50,70", 0.5);
    } catch (e) {
      console.log(e);
    }
  });*/
  async function onUploadPDF(e) {
    const files = e.target.files || (e.dataTransfer && e.dataTransfer.files);
    const file = files[0];
    if (!file || file.type !== "application/pdf") return;
    selectedPageIndex = -1;
    try {
      await addPDF(file);
      selectedPageIndex = 0;
      pdfZoomValue = 1;
    } catch (e) {
      console.log(e);
    }
  }
  async function addPDF(file) {
    try {
      const pdf = await readAsPDF(file);
      pdfName = file.name;
      pdfFile = file;
      const numPages = pdf.numPages;
      pages = Array(numPages)
        .fill()
        .map((_, i) => pdf.getPage(i + 1));
      allObjects = pages.map(() => []);
      pagesScale = Array(numPages).fill(1);
    } catch (e) {
      console.log("Failed to add pdf.");
      throw e;
    }
  }
  async function onUploadImage(e) {
    const file = e.target.files[0];
    if (file && selectedPageIndex >= 0) {
      addImage(file);
    }
    e.target.value = null;
  }
  async function addImage(file) {
    try {
      // get dataURL to prevent canvas from tainted
      const url = await readAsDataURL(file);
      const img = await readAsImage(url);
      const id = genID();
      const { width, height } = img;
      const object = {
        id,
        type: "image",
        width,
        height,
        x: 0,
        y: 0,
        payload: img,
        file
      };
      allObjects = allObjects.map((objects, pIndex) =>
        pIndex === selectedPageIndex ? [...objects, object] : objects
      );
    } catch (e) {
      console.log(`Fail to add image.`, e);
    }
  }
  function onAddTextField() {
    if (selectedPageIndex >= 0) {
      addTextField();
    }
  }
  function addTextField(text = "New Text Field") {
    const id = genID();
    fetchFont(currentFont);
    const object = {
      id,
      text,
      type: "text",
      size: 16,
      lineHeight: 1.4,
      fontFamily: currentFont,
      x: 0,
      y: 0
    };
    allObjects = allObjects.map((objects, pIndex) =>
      pIndex === selectedPageIndex ? [...objects, object] : objects
    );
  }
  function onAddDrawing() {
    if (selectedPageIndex >= 0) {
      addingDrawing = true;
    }
  }
  function addDrawing(originWidth, originHeight, path, scale = 1) {
    const id = genID();
    const object = {
      id,
      path,
      type: "drawing",
      x: 0,
      y: 0,
      originWidth,
      originHeight,
      width: originWidth * scale,
      scale
    };
    allObjects = allObjects.map((objects, pIndex) =>
      pIndex === selectedPageIndex ? [...objects, object] : objects
    );
  }
  function selectFontFamily(event) {
    const name = event.detail.name;
    fetchFont(name);
    currentFont = name;
  }
  function selectPage(index) {
    selectedPageIndex = index;
  }
  function updateObject(objectId, payload) {
    allObjects = allObjects.map((objects, pIndex) =>
      pIndex == selectedPageIndex
        ? objects.map(object =>
            object.id === objectId ? { ...object, ...payload } : object
          )
        : objects
    );
  }
  function onMeasure(scale, i) {
    pagesScale[i] = scale;
  }
  function zoomIn() {
    if ( pdfZoomValue < 4 ) {
      pdfZoomValue+=0.5;
      checkDocumentWidth( pdfZoomValue );
    } else {
      console.log( 'upper zoom limit = 4' );
    }
  }
  function zoomOut() {
    if ( pdfZoomValue > 1 ) {
      pdfZoomValue-=0.5;
      checkDocumentWidth( pdfZoomValue );
    } else {
      console.log( 'lower zoom limit = 1' )
    }
  }
  function checkDocumentWidth( zoomValue ) {
    let elem = document.getElementsByClassName( 'width-check' );
    if ( zoomValue > 2 ) {
      for ( let i = 0; i < elem.length; i++ ) {
        const element = elem[i];
        element.classList.remove( 'items-center' );
      }
    } else {
      for ( let i = 0; i < elem.length; i++ ) {
        const element = elem[i];
        element.classList.add( 'items-center' );
      }
    }
  }
  function goPrint( e ) {
    let frames = document.getElementsByTagName( 'iframe' );
    for (let i = 0; i < frames.length; i++) {
      const frame = frames[i];
      frame.contentWindow.focus();
      console.log(frame.contentWindow, frame);
      frame.contentWindow.print();
    }
    console.log('print action');
  }
  // FIXME: Should wait all objects finish their async work
  async function savePDF() {
    if (!pdfFile || saving || !pages.length) return;
    saving = true;
    try {
      await save(pdfFile, allObjects, pdfName, pdfZoomValue);
    } catch (e) {
      console.log(e);
    } finally {
      saving = false;
    }
  }
</script>
<style>
  .bg-green {
    background-color: #396805;
  }
  .header-bg {
    background-color: #999966;
  }
  .header-bg .rounded-sm>label>img {
    padding: 5px;
  }
  .info-block {
    border: 4px dashed #a0aec0;
    padding: 100px 50px;
    text-align: center;
    color: #a0aec0;
  }
  .main-bg {
    background-color: #ededd3;
  }
  .selected-page {
    border: 3px solid #396805;
  }
</style>
<svelte:window
  on:dragenter|preventDefault
  on:dragover|preventDefault
  on:drop|preventDefault={onUploadPDF} />
<Tailwind />
<main class="flex flex-col items-center py-16 main-bg min-h-screen">
  <div class="fixed z-10 top-0 left-0 right-0 h-12 flex justify-center items-center border-b header-bg">
    <input
      type="file"
      name="pdf"
      id="pdf"
      on:change={onUploadPDF}
      class="hidden" />
    <input
      type="file"
      id="image"
      name="image"
      class="hidden"
      on:change={onUploadImage} />
    <label
      class="text-white font-bold py-1 px-3 md:px-4 rounded mr-3 cursor-pointer md:mr-4 bg-green"
      for="pdf">
      Open PDF File
    </label>
    <div class="relative mr-3 flex h-8 rounded-sm overflow-hidden md:mr-4">
      <label
        class="flex items-center justify-center h-full w-8 cursor-pointer"
        for="image"
        class:cursor-not-allowed={selectedPageIndex < 0}>
        <img src="stamp.svg" alt="An icon for adding images" />
      </label>
      <label
        class="flex items-center justify-center h-full w-8 cursor-pointer"
        for="text"
        class:cursor-not-allowed={selectedPageIndex < 0}
        on:click={onAddTextField}>
        <img src="text.svg" alt="An icon for adding text" />
      </label>
      <!-- svelte-ignore a11y-label-has-associated-control -->
      <label
        class="flex items-center justify-center h-full w-8 cursor-pointer"
        on:click={onAddDrawing}
        class:cursor-not-allowed={selectedPageIndex < 0}>
        <img src="sign.svg" alt="An icon for adding drawing" />
      </label>
      <!-- svelte-ignore a11y-label-has-associated-control -->
      <label
        class="flex items-center justify-center h-full w-8 cursor-pointer"
        class:cursor-not-allowed={selectedPageIndex < 0}
        on:click={zoomIn}>
        <img src="plus.svg" width="24px" heigth="24px" alt="An icon zoom in" />
      </label>
      <!-- svelte-ignore a11y-label-has-associated-control -->
      <label
        class="flex items-center justify-center h-full w-8 cursor-pointer"
        class:cursor-not-allowed={selectedPageIndex < 0}
        on:click={zoomOut}>
        <img src="minus.svg" width="24px" heigth="24px" alt="An icon for zoom out" />
      </label>
    </div>
    <div class="justify-center mr-3 md:mr-4 w-full max-w-xs hidden md:flex">
      <img src="edit.svg" class="mr-2" alt="a pen, edit pdf name" />
      <input
        placeholder="Rename your PDF here"
        type="text"
        class="flex-grow bg-transparent"
        bind:value={pdfName} />
    </div>
    <button
      on:click={savePDF}
      class="w-20 text-white font-bold py-1 px-3 md:px-4 mr-3 md:mr-4 rounded bg-green"
      class:cursor-not-allowed={pages.length === 0 || saving || !pdfFile}
      class:bg-blue-700={pages.length === 0 || saving || !pdfFile}>
      {saving ? 'Saving' : 'Save'}
    </button>
    <!-- svelte-ignore a11y-label-has-associated-control -->
    <label
      class="flex items-center justify-center h-full w-8 cursor-pointer"
      on:click={goPrint}
      class:cursor-not-allowed={selectedPageIndex < 0}>
      <img src="print.svg" width="24px" heigth="24px" alt="Print doccument" />
    </label>
  </div>
  {#if addingDrawing}
    <div
      transition:fly={{ y: -200, duration: 500 }}
      class="fixed z-10 top-0 left-0 right-0 border-b border-gray-300 bg-white
      shadow-lg"
      style="height: 50%;">
      <DrawingCanvas
        on:finish={e => {
          const { originWidth, originHeight, path } = e.detail;
          let scale = 1;
          if (originWidth > 500) {
            scale = 500 / originWidth;
          }
          addDrawing(originWidth, originHeight, path, scale);
          addingDrawing = false;
        }}
        on:cancel={() => (addingDrawing = false)} />
    </div>
  {/if}
  {#if pages.length}
    <div class="flex justify-center px-5 w-full md:hidden">
      <img src="edit.svg" class="mr-2" alt="a pen, edit pdf name" />
      <input
        placeholder="Rename File here"
        type="text"
        class="flex-grow bg-transparent"
        bind:value={pdfName} />
    </div>
    <div class="w-full">
      {#each pages as page, pIndex (page)}
        <div class="p-5 w-full flex flex-col items-center width-check"
          on:mousedown={() => selectPage(pIndex)}
          on:touchstart={() => selectPage(pIndex)}>
          <div class="relative"
            class:selected-page={pIndex === selectedPageIndex}>
            <PDFPage
              on:measure={e => onMeasure(e.detail.scale, pIndex)}
              {pdfZoomValue}
              {page} />
            <div class="absolute top-0 left-0 transform origin-top-left" style="transform: scale({pagesScale[pIndex]}); touch-action: none;">
              {#each allObjects[pIndex] as object (object.id)}
                {#if object.type === 'image'}
                  <Image
                    on:update={e => updateObject(object.id, e.detail)}
                    file={object.file}
                    payload={object.payload}
                    x={object.x}
                    y={object.y}
                    width={object.width}
                    height={object.height}
                    pageScale={pagesScale[pIndex]} />
                {:else if object.type === 'text'}
                  <Text
                    on:update={e => updateObject(object.id, e.detail)}
                    on:selectFont={selectFontFamily}
                    text={object.text}
                    x={object.x}
                    y={object.y}
                    size={object.size}
                    lineHeight={object.lineHeight}
                    fontFamily={object.fontFamily}
                    pageScale={pagesScale[pIndex]} />
                {:else if object.type === 'drawing'}
                  <Drawing
                    on:update={e => updateObject(object.id, e.detail)}
                    path={object.path}
                    x={object.x}
                    y={object.y}
                    width={object.width}
                    originWidth={object.originWidth}
                    originHeight={object.originHeight}
                    pageScale={pagesScale[pIndex]} />
                {/if}
              {/each}
            </div>
          </div>
        </div>
      {/each}
    </div>
  {:else}
    <div class="w-full flex-grow flex justify-center items-center">
      <div class="text-3xl info-block">
        Drag PDF file here,<br />
        or<br />
        Click "Open PDF file" button above<br />
        and browse to file and click open<br />
      </div>
    </div>
  {/if}
</main>
