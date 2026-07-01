
const tipoVeiculo  = document.getElementById("tipoVeiculo");
const marcas       = document.getElementById("marcas");
const modelos      = document.getElementById("modelos");
const anos         = document.getElementById("anos");
const btnConsultar = document.getElementById("consultar");

const fipeTipoMap = { 'CARRO': 'carros', 'MOTO': 'motos', 'CAMINHÃO': 'caminhoes' };
function fipeTipo() { return fipeTipoMap[tipoVeiculo.value] || tipoVeiculo.value; }

function resetSelect(select, defaultText) {
    select.innerHTML = `<option value="">${defaultText}</option>`;
    select.disabled = true;
}

function setHidden(id, val) {
    const el = document.getElementById(id);
    if (el) el.value = val || '';
}

/* ── Fluxo normal (novo veículo) ── */
tipoVeiculo.addEventListener("change", async () => {
    resetSelect(marcas,  "Selecione a marca");
    resetSelect(modelos, "Selecione o modelo");
    resetSelect(anos,    "Selecione o ano");
    setHidden('fipe_marca_cod', '');
    setHidden('fipe_modelo_cod', '');
    btnConsultar.disabled = true;
    if (!tipoVeiculo.value) return;

    marcas.disabled = true;
    try {
        const r = await fetch(`https://parallelum.com.br/fipe/api/v1/${fipeTipo()}/marcas`);
        const d = await r.json();
        d.forEach(m => marcas.appendChild(new Option(m.nome, m.codigo)));
    } catch(e) { marcas.disabled = false; return; }
    marcas.disabled = false;
});

marcas.addEventListener("change", async () => {
    resetSelect(modelos, "Selecione o modelo");
    resetSelect(anos,    "Selecione o ano");
    setHidden('fipe_marca_cod', marcas.value);
    setHidden('fipe_modelo_cod', '');
    btnConsultar.disabled = true;
    if (!marcas.value) return;

    modelos.disabled = true;
    try {
        const r = await fetch(`https://parallelum.com.br/fipe/api/v1/${fipeTipo()}/marcas/${marcas.value}/modelos`);
        const d = await r.json();
        d.modelos.forEach(m => modelos.appendChild(new Option(m.nome, m.codigo)));
    } catch(e) { modelos.disabled = false; return; }
    modelos.disabled = false;
});

modelos.addEventListener("change", async () => {
    resetSelect(anos, "Selecione o ano");
    setHidden('fipe_modelo_cod', modelos.value);
    btnConsultar.disabled = true;
    if (!modelos.value) return;

    anos.disabled = true;
    try {
        const r = await fetch(`https://parallelum.com.br/fipe/api/v1/${fipeTipo()}/marcas/${marcas.value}/modelos/${modelos.value}/anos`);
        const d = await r.json();
        d.forEach(a => anos.appendChild(new Option(a.nome, a.codigo)));
    } catch(e) { anos.disabled = false; return; }
    anos.disabled = false;
});

anos.addEventListener("change", () => {
    btnConsultar.disabled = !anos.value;
});

btnConsultar.addEventListener("click", async () => {
    if (!anos.value) return;
    const r = await fetch(`https://parallelum.com.br/fipe/api/v1/${fipeTipo()}/marcas/${marcas.value}/modelos/${modelos.value}/anos/${anos.value}`);
    const d = await r.json();
    document.getElementById("marca").value         = d.Marca;
    document.getElementById("modelo").value        = d.Modelo;
    document.getElementById("ano").value           = d.AnoModelo;
    document.getElementById("anoModelo").value     = d.AnoModelo;
    document.getElementById("combustivel").value   = d.Combustivel;
    document.getElementById("valor").value         = d.Valor;
    document.getElementById("valorCobertura").value = d.Valor;
    document.getElementById("codigoFipe").value    = d.CodigoFipe;
    document.getElementById("mesReferencia").value = d.MesReferencia;
    /* Garante que os códigos FIPE ficam gravados */
    setHidden('fipe_marca_cod',  marcas.value);
    setHidden('fipe_modelo_cod', modelos.value);
});

/* ── Função dedicada para pré-carregar marca/modelo no modo edição ──
   Parâmetros:
     tipo        – 'CARRO' | 'MOTO' | 'CAMINHÃO'
     marcaQuery  – texto OU código numérico FIPE da marca (fallback)
     modeloQuery – texto OU código numérico FIPE do modelo (fallback)
     marcaCod    – código numérico FIPE da marca  (preferencial, sem falha de texto)
     modeloCod   – código numérico FIPE do modelo (preferencial)
*/
window.fipeCarregarParaEdicao = async function(tipo, marcaQuery, modeloQuery, marcaCod, modeloCod) {
    if (!tipo) return;

    const tipoApi = fipeTipoMap[tipo] || tipo;

    /* 1. Carrega marcas */
    resetSelect(marcas,  "Selecione a marca");
    resetSelect(modelos, "Selecione o modelo");
    resetSelect(anos,    "Selecione o ano");
    btnConsultar.disabled = true;
    marcas.disabled = true;

    try {
        const r = await fetch(`https://parallelum.com.br/fipe/api/v1/${tipoApi}/marcas`);
        const d = await r.json();
        d.forEach(m => marcas.appendChild(new Option(m.nome, m.codigo)));
    } catch(e) { marcas.disabled = false; return; }
    marcas.disabled = false;

    /* 2. Seleciona a marca — código FIPE direto tem prioridade */
    let selectedMarcaCod;
    if (marcaCod) {
        const opt = Array.from(marcas.options).find(o => o.value === String(marcaCod));
        if (!opt) return;
        marcas.value = opt.value;
        selectedMarcaCod = opt.value;
    } else if (marcaQuery) {
        const q = marcaQuery.trim().toLowerCase();
        const opt = Array.from(marcas.options).find(
            o => o.text.trim().toLowerCase() === q || o.value === marcaQuery.trim()
        );
        if (!opt) return;
        marcas.value = opt.value;
        selectedMarcaCod = opt.value;
    } else {
        return;
    }

    setHidden('fipe_marca_cod', selectedMarcaCod);

    /* 3. Carrega modelos */
    modelos.disabled = true;
    try {
        const r = await fetch(`https://parallelum.com.br/fipe/api/v1/${tipoApi}/marcas/${selectedMarcaCod}/modelos`);
        const d = await r.json();
        d.modelos.forEach(m => modelos.appendChild(new Option(m.nome, m.codigo)));
    } catch(e) { modelos.disabled = false; return; }
    modelos.disabled = false;

    /* 4. Seleciona o modelo — código FIPE direto tem prioridade */
    let selectedModeloCod;
    if (modeloCod) {
        const opt = Array.from(modelos.options).find(o => o.value === String(modeloCod));
        if (!opt) return;
        modelos.value = opt.value;
        selectedModeloCod = opt.value;
    } else if (modeloQuery) {
        const q = modeloQuery.trim().toLowerCase();
        const opt = Array.from(modelos.options).find(
            o => o.text.trim().toLowerCase() === q || o.value === modeloQuery.trim()
        );
        if (!opt) return;
        modelos.value = opt.value;
        selectedModeloCod = opt.value;
    } else {
        return;
    }

    setHidden('fipe_modelo_cod', selectedModeloCod);

    /* 5. Carrega anos */
    anos.disabled = true;
    try {
        const r = await fetch(`https://parallelum.com.br/fipe/api/v1/${tipoApi}/marcas/${selectedMarcaCod}/modelos/${selectedModeloCod}/anos`);
        const d = await r.json();
        d.forEach(a => anos.appendChild(new Option(a.nome, a.codigo)));
    } catch(e) { anos.disabled = false; return; }
    anos.disabled = false;
};
