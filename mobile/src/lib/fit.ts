/**
 * O maior tamanho de fonte (até `max`) em que uma linha cabe na largura disponível, para um título
 * que não pode quebrar no lugar errado. `perPoint` é a largura da linha a cada ponto de tamanho,
 * medida na própria fonte. `fontScale` é o tamanho de fonte do sistema: como o texto já cresce com
 * ele, a conta divide por ele, e a linha continua inteira mesmo com a fonte do celular maior.
 */
export function fitFontSize(available: number, perPoint: number, max: number, fontScale = 1): number {
  return Math.min(max, Math.floor(available / (perPoint * fontScale)));
}
