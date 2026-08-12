import{E as t}from"./D83bcZ16.js";const o=()=>{const s=t(),r=n=>s.value?.permissions?.[n]===!0;return{can:r,canAny:(...n)=>n.some(e=>r(e)),canAll:(...n)=>n.every(e=>r(e))}};export{o as u};
