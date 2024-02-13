/*! third party licenses: dist/vendor.LICENSE.txt */
function s(o){const e={};for(const n of o.keys())e[n]=o.get(n);return e}function u(...o){if(o.length===0)return{};const e={};return o.reduce((n,c)=>(Object.keys(c).forEach(t=>{const r=t.toLowerCase();e.hasOwnProperty(r)?n[e[r]]=c[t]:(e[r]=t,n[t]=c[t])}),n),{})}export{s as c,u as m};
