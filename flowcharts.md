### vendor and product management ###

```mermaid
graph TD;
    A(manage vendors)-->B[edit existing vendor];
    A-->C[new vendor];
    B-->D[add documents, update info, set pricelist filter, import pricelist];
    C-->D;
    E(manage products)-->F[edit existing product];
    E-->G[add new product];
```

```mermaid
graph TD;
    B-->A;
    A-->C;
    B-->D;
    C-->D;
```